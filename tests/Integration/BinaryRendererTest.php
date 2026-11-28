<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Core\Theme;
use PHPUnit\Framework\TestCase;

final class BinaryRendererTest extends TestCase
{
    use RequiresD2Binary;

    private BinaryRenderer $renderer;

    protected function setUp(): void
    {
        self::requireD2Binary();

        $this->renderer = new BinaryRenderer();
    }

    public function testItRendersSvg(): void
    {
        $svg = $this->renderer->render(new Diagram('a -> b'));

        self::assertStringContainsString('<svg', $svg);
        self::assertStringEndsWith('</svg>', trim($svg));
    }

    public function testOptionsChangeTheOutput(): void
    {
        $diagram = new Diagram('a -> b');
        $default = $this->renderer->render($diagram);

        self::assertNotSame($default, $this->renderer->render($diagram->withOptions(new RenderOptions(sketch: true))));
        self::assertNotSame($default, $this->renderer->render($diagram->withOptions(new RenderOptions(theme: 200))));
        self::assertNotSame($default, $this->renderer->render($diagram->withOptions(new RenderOptions(pad: 0))));
    }

    public function testTypedOptionsAreAcceptedByD2(): void
    {
        $svg = $this->renderer->render(new Diagram('a -> b', new RenderOptions(
            theme: Theme::NeutralDefault,
            layout: Layout::Elk,
            darkTheme: Theme::DarkMauve,
            center: true,
            scale: 0.5,
        )));

        self::assertStringContainsString('<svg', $svg);
        // The dark theme is applied through a prefers-color-scheme media query.
        self::assertStringContainsString('prefers-color-scheme:dark', $svg);
    }

    public function testFontsArePassedToD2(): void
    {
        $this->expectException(RenderException::class);
        $this->expectExceptionMessage('/nonexistent/regular.ttf');

        $this->renderer->render(new Diagram('a -> b', (new RenderOptions())->withFonts(regular: '/nonexistent/regular.ttf')));
    }

    public function testSyntaxErrorThrowsRenderExceptionWithRawD2Error(): void
    {
        try {
            $this->renderer->render(new Diagram("a -> b\nc: {"));
            self::fail('A RenderException was expected.');
        } catch (RenderException $e) {
            self::assertNotSame('', trim($e->getD2Error()));
            self::assertSame(2, $e->getD2Line());
            self::assertSame(4, $e->getD2Column());
            self::assertSame('D2 syntax error at line 2, column 4: maps must be terminated with }', $e->getMessage());
        }
    }

    public function testItWritesNoFileInTheWorkingDirectory(): void
    {
        $cwd = (string) getcwd();
        $before = scandir($cwd);

        $this->renderer->render(new Diagram('a -> b'));

        self::assertSame($before, scandir($cwd));
    }

    public function testItReportsTheD2Version(): void
    {
        self::assertMatchesRegularExpression('/^v?\d+\.\d+\.\d+/', $this->renderer->getVersion());
    }
}
