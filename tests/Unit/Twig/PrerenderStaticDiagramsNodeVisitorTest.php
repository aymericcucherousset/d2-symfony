<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class PrerenderStaticDiagramsNodeVisitorTest extends TestCase
{
    public function testStaticDiagramIsRenderedOnceAtCompileTime(): void
    {
        $spy = new SpyRenderer('<svg>static</svg>');
        $twig = self::twig($spy, ['page' => "<p>{% d2 %}\na -> b\n{% endd2 %}</p>"]);

        $php = $twig->compileSource($twig->getLoader()->getSourceContext('page'));
        self::assertStringContainsString('<svg>static</svg>', $php);
        self::assertStringNotContainsString('renderTag(', $php);

        $template = $twig->load('page');
        for ($i = 0; $i < 3; ++$i) {
            self::assertSame('<p><svg>static</svg></p>', $template->render());
        }

        self::assertCount(2, $spy->diagrams, 'Rendered by compileSource() and by the compilation in load(), never by render().');
        self::assertSame("a -> b\n", $spy->diagrams[0]->source);
    }

    public function testConstantOptionsAreMergedOverDefaults(): void
    {
        $spy = new SpyRenderer();
        $twig = self::twig($spy, ['page' => "{% d2 with { sketch: true, layout: 'elk' } %}a{% endd2 %}"], new RenderOptions(theme: 1));

        $twig->load('page');

        self::assertCount(1, $spy->diagrams);
        self::assertEquals(new RenderOptions(theme: 1, layout: 'elk', sketch: true), $spy->diagrams[0]->options);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dynamicTemplateProvider(): iterable
    {
        yield 'variable in body' => ['{% d2 %}a -> {{ target }}{% endd2 %}'];
        yield 'tag in body' => ['{% d2 %}{% if true %}a{% endif %}{% endd2 %}'];
        yield 'variable options' => ['{% d2 with opts %}a{% endd2 %}'];
        yield 'computed option' => ['{% d2 with { pad: 1 + 1 } %}a{% endd2 %}'];
        yield 'nested array option' => ['{% d2 with { pad: [1] } %}a{% endd2 %}'];
    }

    #[DataProvider('dynamicTemplateProvider')]
    public function testDynamicDiagramsAreRenderedAtRuntime(string $template): void
    {
        $spy = new SpyRenderer();
        $twig = self::twig($spy, ['page' => $template]);

        $php = $twig->compileSource($twig->getLoader()->getSourceContext('page'));

        self::assertStringContainsString('renderTag(', $php);
        self::assertSame([], $spy->diagrams);
    }

    public function testDiagramFailingAtCompileTimeFailsAtRuntimeWithTheTemplateLine(): void
    {
        $renderer = new FailingRenderer("err: failed to compile -: -:2:4: maps must be terminated with }\n");
        $twig = self::twig($renderer, ['page' => "<html>\n{% d2 %}\na\nc: {\n{% endd2 %}"]);

        $template = $twig->load('page');

        try {
            $template->render();
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(4, $e->getTemplateLine());
            self::assertInstanceOf(RenderException::class, $e->getPrevious());
        }
    }

    public function testPrerenderingIsDisabledByDefault(): void
    {
        $spy = new SpyRenderer();
        $twig = self::twig($spy, ['page' => '{% d2 %}a{% endd2 %}'], prerender: false);

        $twig->load('page');

        self::assertCount(0, $spy->diagrams);
        self::assertSame('<svg>rendered</svg>', $twig->render('page'));
        self::assertCount(1, $spy->diagrams);
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(Renderer $renderer, array $templates, RenderOptions $defaults = new RenderOptions(), bool $prerender = true): Environment
    {
        $twig = new Environment(new ArrayLoader($templates));
        $twig->addExtension($prerender ? new D2Extension(true) : new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime($renderer, $defaults),
        ]));

        return $twig;
    }
}
