<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Command;

use Aymericcucherousset\D2Symfony\Command\RenderCommand;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\UnavailableRenderer;
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

    public function testInvalidThemeIsRejected(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, '--theme' => 'dark']));
        self::assertStringContainsString('must be a theme ID', $tester->getDisplay());
        self::assertSame([], $this->renderer->diagrams);
    }

    public function testInvalidLayoutIsRejected(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['input' => $this->input, '--layout' => 'ELK!']));
        self::assertStringContainsString('layout engine "ELK!" is invalid', $tester->getDisplay());
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
