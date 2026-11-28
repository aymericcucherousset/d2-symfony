<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Twig\D2FileLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class D2FileLoaderTest extends TestCase
{
    private const ROOT = __DIR__.'/../../Fixtures/diagrams';

    private ?string $tempDir = null;

    protected function tearDown(): void
    {
        if (null !== $this->tempDir) {
            (new Filesystem())->remove($this->tempDir);
        }
    }

    public function testLoadsFileRelativeToRootDirectory(): void
    {
        self::assertSame("user -> app: HTTPS\napp -> db\n", (new D2FileLoader(self::ROOT))->load('architecture.d2'));
    }

    public function testLoadsFileInSubdirectory(): void
    {
        $loader = new D2FileLoader(self::ROOT.'/');

        self::assertSame("a -> b\n", $loader->load('nested/flow.d2'));
        self::assertSame("a -> b\n", $loader->load('./nested/../nested/flow.d2'));
    }

    public function testExtensionIsCaseInsensitive(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir.'/UPPER.D2', 'x -> y');

        self::assertSame('x -> y', (new D2FileLoader($dir))->load('UPPER.D2'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPaths(): iterable
    {
        yield 'empty' => ['', 'must be a non-empty string'];
        yield 'null byte' => ["architecture.d2\0.txt", 'must be a non-empty string'];
        yield 'absolute' => ['/etc/passwd.d2', 'must be relative'];
        yield 'absolute Windows' => ['C:\\diagrams\\a.d2', 'must be relative'];
        yield 'stream wrapper' => ['php://filter/resource=a.d2', 'must be relative'];
        yield 'parent directory' => ['../outside.d2', 'is outside of'];
        yield 'nested parent directory' => ['nested/../../outside.d2', 'is outside of'];
        yield 'wrong extension' => ['not-a-diagram.txt', 'must have the ".d2" extension'];
        yield 'traversal to non-d2 file' => ['../../../composer.json', 'must have the ".d2" extension'];
        yield 'missing file' => ['missing.d2', 'does not exist in'];
        yield 'directory' => ['nested.d2', 'does not exist in'];
    }

    #[DataProvider('invalidPaths')]
    public function testInvalidPathIsRejected(string $path, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new D2FileLoader(self::ROOT))->load($path);
    }

    public function testDirectoryWithD2ExtensionIsRejected(): void
    {
        $dir = $this->tempDir();
        mkdir($dir.'/dir.d2');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in');

        (new D2FileLoader($dir))->load('dir.d2');
    }

    public function testSymbolicLinkPointingOutsideRootIsRejected(): void
    {
        $dir = $this->tempDir();
        mkdir($dir.'/root');
        file_put_contents($dir.'/secret.d2', 'secret');
        if (!@symlink($dir.'/secret.d2', $dir.'/root/link.d2')) {
            self::markTestSkipped('Symbolic links are not supported.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is outside of');

        (new D2FileLoader($dir.'/root'))->load('link.d2');
    }

    public function testSiblingDirectorySharingThePrefixIsRejected(): void
    {
        $dir = $this->tempDir();
        mkdir($dir.'/root');
        mkdir($dir.'/root-other');
        file_put_contents($dir.'/root-other/a.d2', 'a');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is outside of');

        (new D2FileLoader($dir.'/root'))->load('../root-other/a.d2');
    }

    public function testMissingRootDirectoryIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('root directory "/does/not/exist" does not exist');

        (new D2FileLoader('/does/not/exist'))->load('a.d2');
    }

    public function testEmptyRootDirectoryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new D2FileLoader('');
    }

    private function tempDir(): string
    {
        $this->tempDir = sys_get_temp_dir().'/d2-file-loader-'.bin2hex(random_bytes(6));
        mkdir($this->tempDir);

        return $this->tempDir;
    }
}
