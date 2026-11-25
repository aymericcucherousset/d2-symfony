<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
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

    public function testSyntaxErrorThrowsRenderExceptionWithRawD2Error(): void
    {
        try {
            $this->renderer->render(new Diagram("a -> b\nc: {"));
            self::fail('A RenderException was expected.');
        } catch (RenderException $e) {
            self::assertNotSame('', trim($e->getD2Error()));
            self::assertMatchesRegularExpression('/\d+:\d+/', $e->getD2Error());
        }
    }

    public function testItWritesNoFileInTheWorkingDirectory(): void
    {
        $cwd = (string) getcwd();
        $before = scandir($cwd);

        $this->renderer->render(new Diagram('a -> b'));

        self::assertSame($before, scandir($cwd));
    }
}
