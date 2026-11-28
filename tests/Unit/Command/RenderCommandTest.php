<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Command;

use Aymericcucherousset\D2Symfony\Command\RenderCommand;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Core\Theme;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\UnavailableRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class RenderCommandTest extends TestCase
{
    private string $dir;
    private string $input;
    private SpyRenderer $renderer;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/d2-render-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->input = $this->dir.'/diagram.d2';
        file_put_contents($this->input, "a -> b\n");
        $this->renderer = new SpyRenderer('<svg>rendered</svg>');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testSvgIsWrittenToStdoutByDefault(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['input' => $this->input], ['capture_stderr_separately' => true]));
        self::assertSame('<svg>rendered</svg>', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
        self::assertSame("a -> b\n", $this->renderer->diagrams[0]->source);
    }

    public function testSvgIsWrittenToTheOutputFile(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['input' => $this->input, '--output' => $this->dir.'/out.svg'], ['capture_stderr_separately' => true]));
        self::assertStringEqualsFile($this->dir.'/out.svg', '<svg>rendered</svg>');
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Diagram rendered to', $tester->getErrorOutput());
    }

    public function testSourceCanBeReadFromStdin(): void
    {
        $tester = $this->tester();
        $tester->setInputs(['x -> y']);

        self::assertSame(Command::SUCCESS, $tester->execute(['input' => '-']));
        self::assertStringStartsWith('x -> y', $this->renderer->diagrams[0]->source);
    }

    public function testOptionsOverrideTheDefaults(): void
    {
        $tester = $this->tester(defaults: new RenderOptions(theme: 1, layout: 'dagre', pad: 10, sketch: true));

        $tester->execute(['input' => $this->input, '--theme' => '200', '--layout' => 'elk', '--no-sketch' => true]);

        self::assertEquals(new RenderOptions(theme: 200, layout: 'elk', pad: 10, sketch: false), $this->renderer->diagrams[0]->options);
    }

    public function testDefaultsAreUsedWithoutOptions(): void
    {
        $defaults = new RenderOptions(theme: 1, sketch: true);

        $this->tester(defaults: $defaults)->execute(['input' => $this->input]);

        self::assertEquals($defaults, $this->renderer->diagrams[0]->options);
    }

    public function testSketchCanBeEnabled(): void
    {
        $this->tester()->execute(['input' => $this->input, '--sketch' => true]);

        self::assertTrue($this->renderer->diagrams[0]->options->sketch);
    }

    public function testAllOptionsCanBeGiven(): void
    {
        $tester = $this->tester(defaults: new RenderOptions(center: true));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'input' => $this->input,
            '--theme' => '0',
            '--dark-theme' => '200',
            '--pad' => '0',
            '--scale' => '0.75',
            '--no-center' => true,
        ]));

        self::assertEquals(
            new RenderOptions(theme: Theme::NeutralDefault, pad: 0, darkTheme: Theme::DarkMauve, scale: 0.75),
            $this->renderer->diagrams[0]->options,
        );
    }

    public function testCenterCanBeEnabled(): void
    {
        $this->tester()->execute(['input' => $this->input, '--center' => true]);

        self::assertTrue($this->renderer->diagrams[0]->options->center);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidNumericOptionProvider(): iterable
    {
        yield 'dark theme' => [['--dark-theme' => 'mauve'], 'The "--dark-theme" option must be a positive integer, "mauve" given.'];
        yield 'unknown dark theme' => [['--dark-theme' => '42'], 'Unknown d2 theme ID 42 for "dark_theme"'];
        yield 'pad' => [['--pad' => '-1'], 'The "--pad" option must be a positive integer, "-1" given.'];
        yield 'scale' => [['--scale' => 'half'], 'The "--scale" option must be a number, "half" given.'];
        yield 'zero scale' => [['--scale' => '0'], 'The scale must be a number greater than 0'];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidNumericOptionProvider')]
    public function testInvalidOptionValuesAreRejected(array $options, string $message): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, ...$options]));
        self::assertStringContainsString($message, preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        self::assertSame([], $this->renderer->diagrams);
    }

    public function testInvalidThemeIsRejected(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, '--theme' => 'dark']));
        self::assertStringContainsString('The "--theme" option must be a positive integer', $tester->getDisplay());
        self::assertSame([], $this->renderer->diagrams);
    }

    public function testInvalidLayoutIsRejected(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, '--layout' => 'ELK!']));
        self::assertStringContainsString('Unknown layout engine "ELK!"', $tester->getDisplay());
    }

    public function testMissingInputFileIsReported(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->dir.'/missing.d2']));
        self::assertStringContainsString('does not exist', $tester->getDisplay());
    }

    public function testEmptySourceIsReported(): void
    {
        file_put_contents($this->input, "  \n");
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input]));
        self::assertStringContainsString('must not be empty', $tester->getDisplay());
    }

    public function testD2ErrorsPointToTheInputFile(): void
    {
        $tester = $this->tester(new FailingRenderer("err: failed to compile -: -:2:5: unexpected token\n"));

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input]));
        self::assertStringContainsString('D2 syntax error at line 2, column 5', $tester->getDisplay());
        self::assertStringContainsString($this->input.':2:5: unexpected token', $tester->getDisplay());
    }

    public function testUnavailableRendererIsReported(): void
    {
        $tester = $this->tester(new UnavailableRenderer());

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input]));
    }

    public function testUnwritableOutputIsReported(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, '-o' => $this->dir.'/missing/out.svg']));
        self::assertStringContainsString('Unable to write the SVG', $tester->getDisplay());
    }

    private function tester(?Renderer $renderer = null, RenderOptions $defaults = new RenderOptions()): CommandTester
    {
        return new CommandTester(new RenderCommand($renderer ?? $this->renderer, $defaults));
    }
}
