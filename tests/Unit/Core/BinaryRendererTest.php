<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs BinaryRenderer against fake d2 shell scripts (see tests/Fixtures/bin).
 */
final class BinaryRendererTest extends TestCase
{
    private const BIN_DIR = __DIR__.'/../../Fixtures/bin';

    protected function setUp(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Fake d2 binaries are POSIX shell scripts.');
        }
    }

    public function testItPassesOptionsAsArgumentsAndSourceThroughStdin(): void
    {
        $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-echo');
        $diagram = new Diagram('a -> b', new RenderOptions(theme: 1, layout: 'elk', pad: 10, sketch: true));

        self::assertSame(
            "<svg>\narg:--theme=1\narg:--layout=elk\narg:--pad=10\narg:--sketch\narg:-\narg:-\nstdin:a -> b\n</svg>\n",
            $renderer->render($diagram),
        );
    }

    public function testItReadsStdinAndStdoutWithDefaultOptions(): void
    {
        $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-echo');

        self::assertSame("<svg>\narg:-\narg:-\nstdin:x\n</svg>\n", $renderer->render(new Diagram('x')));
    }

    public function testFormatIsRequestedOnStdout(): void
    {
        $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-echo');

        self::assertSame(
            "<svg>\narg:--stdout-format=png\narg:-\narg:-\nstdin:x\n</svg>\n",
            $renderer->render(new Diagram('x', new RenderOptions(format: 'png'))),
        );
    }

    public function testNonZeroExitCodeThrowsRenderExceptionWithRawError(): void
    {
        $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-fail');

        try {
            $renderer->render(new Diagram('a -> {'));
            self::fail('A RenderException was expected.');
        } catch (RenderException $e) {
            self::assertSame("err: failed to compile -: -:1:4: unexpected text after map key\n", $e->getD2Error());
            self::assertSame('D2 syntax error at line 1, column 4: unexpected text after map key', $e->getMessage());
            self::assertSame(1, $e->getD2Line());
            self::assertSame(4, $e->getD2Column());
        }
    }

    public function testEmptyOutputThrowsRenderException(): void
    {
        $this->expectException(RenderException::class);

        (new BinaryRenderer(self::BIN_DIR.'/fake-d2-empty'))->render(new Diagram('a -> b'));
    }

    public function testTimeoutThrowsRenderException(): void
    {
        $this->expectException(RenderException::class);
        $this->expectExceptionMessage('timed out after 0.2 seconds');

        (new BinaryRenderer(self::BIN_DIR.'/fake-d2-sleep', 0.2))->render(new Diagram('a -> b'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function missingBinaryProvider(): iterable
    {
        yield 'unknown name in PATH' => ['d2-does-not-exist-'.bin2hex(random_bytes(4))];
        yield 'missing path' => [self::BIN_DIR.'/does-not-exist'];
        yield 'directory' => [self::BIN_DIR];
        yield 'non-executable file' => [self::BIN_DIR.'/not-executable'];
    }

    #[DataProvider('missingBinaryProvider')]
    public function testMissingOrNonExecutableBinaryThrowsBinaryNotFoundException(string $binary): void
    {
        try {
            (new BinaryRenderer($binary))->render(new Diagram('a -> b'));
            self::fail('A BinaryNotFoundException was expected.');
        } catch (BinaryNotFoundException $e) {
            self::assertSame($binary, $e->getBinary());
        }
    }

    /**
     * @return iterable<string, array{string, float|null}>
     */
    public static function invalidConstructorArgumentsProvider(): iterable
    {
        yield 'empty binary' => ['', 60.0];
        yield 'zero timeout' => ['d2', 0.0];
        yield 'negative timeout' => ['d2', -1.0];
    }

    #[DataProvider('invalidConstructorArgumentsProvider')]
    public function testInvalidConstructorArgumentsAreRejected(string $binary, ?float $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BinaryRenderer($binary, $timeout);
    }

    public function testTimeoutCanBeDisabled(): void
    {
        $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-echo', null);

        self::assertStringStartsWith('<svg>', $renderer->render(new Diagram('a')));
    }

    public function testGetVersionRunsTheBinaryOnce(): void
    {
        $callsFile = tempnam(sys_get_temp_dir(), 'd2-calls-');
        self::assertIsString($callsFile);
        // Symfony Process passes $_ENV (not putenv() values) to child processes.
        $_ENV['D2_CALLS_FILE'] = $callsFile;

        try {
            $renderer = new BinaryRenderer(self::BIN_DIR.'/fake-d2-version');

            self::assertSame('v9.9.9', $renderer->getVersion());
            self::assertSame('v9.9.9', $renderer->getVersion());
            self::assertSame(['--version'], file($callsFile, \FILE_IGNORE_NEW_LINES));
        } finally {
            unset($_ENV['D2_CALLS_FILE']);
            unlink($callsFile);
        }
    }

    public function testGetVersionFailureThrowsRenderException(): void
    {
        $this->expectException(RenderException::class);

        (new BinaryRenderer(self::BIN_DIR.'/fake-d2-fail'))->getVersion();
    }

    public function testGetVersionWithMissingBinaryThrowsBinaryNotFoundException(): void
    {
        $this->expectException(BinaryNotFoundException::class);

        (new BinaryRenderer(self::BIN_DIR.'/does-not-exist'))->getVersion();
    }

    public function testCandidatesAreTriedInOrder(): void
    {
        $renderer = new BinaryRenderer([self::BIN_DIR.'/does-not-exist', self::BIN_DIR.'/fake-d2-version', self::BIN_DIR.'/fake-d2-echo']);

        self::assertSame('v9.9.9', $renderer->getVersion());
    }

    public function testMissingCandidatesAreListedWithTheHint(): void
    {
        $renderer = new BinaryRenderer([self::BIN_DIR.'/missing', 'd2-does-not-exist-'.bin2hex(random_bytes(4))], 5.0, 'Run "bin/console d2:download".');

        try {
            $renderer->render(new Diagram('a'));
            self::fail('A BinaryNotFoundException was expected.');
        } catch (BinaryNotFoundException $e) {
            self::assertCount(2, $e->getCandidates());
            self::assertSame(self::BIN_DIR.'/missing', $e->getBinary());
            self::assertStringContainsString('(tried "'.self::BIN_DIR.'/missing", "d2-does-not-exist-', $e->getMessage());
            self::assertStringEndsWith('Run "bin/console d2:download".', $e->getMessage());
        }
    }

    public function testEmptyCandidateListIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BinaryRenderer([]);
    }
}
