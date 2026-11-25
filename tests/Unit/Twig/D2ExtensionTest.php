<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class D2ExtensionTest extends TestCase
{
    public function testFilterRendersSourceFromVariableWithoutEscaping(): void
    {
        $spy = new SpyRenderer('<svg><text>a &amp; b</text></svg>');

        $output = self::twig($spy, ['page' => '<div>{{ src|d2 }}</div>'])->render('page', ['src' => 'a -> b']);

        self::assertSame('<div><svg><text>a &amp; b</text></svg></div>', $output);
        self::assertCount(1, $spy->diagrams);
        self::assertSame('a -> b', $spy->diagrams[0]->source);
        self::assertEquals(new RenderOptions(), $spy->diagrams[0]->options);
    }

    public function testOptionsArgumentIsMergedOverDefaults(): void
    {
        $spy = new SpyRenderer();
        $twig = self::twig($spy, ['page' => "{{ 'a -> b'|d2({ sketch: true, layout: 'elk' }) }}"], new RenderOptions(theme: 1, pad: 5));

        $twig->render('page');

        self::assertEquals(new RenderOptions(theme: 1, layout: 'elk', pad: 5, sketch: true), $spy->diagrams[0]->options);
    }

    public function testNullOptionResetsDefault(): void
    {
        $spy = new SpyRenderer();

        self::twig($spy, ['page' => "{{ 'a'|d2({ theme: null }) }}"], new RenderOptions(theme: 1))->render('page');

        self::assertNull($spy->diagrams[0]->options->theme);
    }

    public function testXmlDeclarationIsStripped(): void
    {
        $spy = new SpyRenderer("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<svg></svg>");

        self::assertSame('<svg></svg>', self::twig($spy, ['page' => "{{ 'a'|d2 }}"])->render('page'));
    }

    public function testStringableSourceIsAccepted(): void
    {
        $spy = new SpyRenderer();

        self::twig($spy, ['page' => '{{ src|d2 }}'])->render('page', ['src' => new Markup('x -> y', 'UTF-8')]);

        self::assertSame('x -> y', $spy->diagrams[0]->source);
    }

    public function testUnknownOptionRaisesTwigRuntimeError(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ 'a'|d2({ them: 1 }) }}", 'Unknown D2 option(s) "them"');
    }

    public function testEmptySourceRaisesTwigRuntimeError(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ ''|d2 }}", 'must not be empty');
    }

    private static function assertRuntimeErrorCausedByInvalidArgument(string $template, string $message): void
    {
        try {
            self::twig(new SpyRenderer(), ['page' => $template])->render('page');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(1, $e->getTemplateLine());
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
            self::assertStringContainsString($message, $e->getPrevious()->getMessage());
        }
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(SpyRenderer $renderer, array $templates, RenderOptions $defaults = new RenderOptions()): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime($renderer, $defaults),
        ]));

        return $twig;
    }
}
