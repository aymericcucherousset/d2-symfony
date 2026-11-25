<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class D2TokenParserTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function outputModeProvider(): iterable
    {
        yield 'echo' => [false];
        yield 'yield' => [true];
    }

    #[DataProvider('outputModeProvider')]
    public function testTagRendersItsBody(bool $useYield): void
    {
        $spy = new SpyRenderer('<svg>&amp;</svg>');
        $twig = self::twig($spy, ['page' => "<p>{% d2 %}\na -> b\n{% endd2 %}</p>"], useYield: $useYield);

        self::assertSame('<p><svg>&amp;</svg></p>', $twig->render('page'));
        self::assertCount(1, $spy->diagrams);
        self::assertSame("a -> b\n", $spy->diagrams[0]->source);
        self::assertEquals(new RenderOptions(), $spy->diagrams[0]->options);
    }

    #[DataProvider('outputModeProvider')]
    public function testBodySupportsTwigVariablesAndTagsWithoutEscaping(bool $useYield): void
    {
        $spy = new SpyRenderer();
        $template = <<<'TWIG'
            {% d2 %}
            user -> app: {{ protocol }}
            {% for service in services %}
            app -> {{ service }}
            {% endfor %}
            {% endd2 %}
            TWIG;
        $twig = self::twig($spy, ['page' => $template], useYield: $useYield);

        $twig->render('page', ['protocol' => '<HTTPS & co>', 'services' => ['db', 'cache']]);

        self::assertSame("user -> app: <HTTPS & co>\napp -> db\napp -> cache\n", $spy->diagrams[0]->source);
    }

    public function testExpressionsWithArrowsAreNotEscaped(): void
    {
        $spy = new SpyRenderer();

        self::twig($spy, ['page' => "{% d2 %}{{ 'a -> b' }}{% endd2 %}"])->render('page');

        self::assertSame('a -> b', $spy->diagrams[0]->source);
    }

    public function testOptionsAreMergedOverDefaults(): void
    {
        $spy = new SpyRenderer();
        $twig = self::twig($spy, ['page' => "{% d2 with { sketch: true, layout: 'elk' } %}a{% endd2 %}"], new RenderOptions(theme: 1));

        $twig->render('page');

        self::assertEquals(new RenderOptions(theme: 1, layout: 'elk', sketch: true), $spy->diagrams[0]->options);
    }

    public function testOptionsCanBeAnyExpression(): void
    {
        $spy = new SpyRenderer();

        self::twig($spy, ['page' => '{% d2 with opts|merge({ pad: 0 }) %}a{% endd2 %}'])->render('page', ['opts' => ['theme' => 200]]);

        self::assertEquals(new RenderOptions(theme: 200, pad: 0), $spy->diagrams[0]->options);
    }

    public function testTagCompilesToARuntimeCall(): void
    {
        $twig = self::twig(new SpyRenderer(), ['page' => '{% d2 with { pad: 1 } %}a{% endd2 %}']);

        $php = $twig->compileSource($twig->getLoader()->getSourceContext('page'));

        self::assertStringContainsString('$this->env->getRuntime("Aymericcucherousset\\\\D2Symfony\\\\Twig\\\\D2Runtime")->render($', $php);
        self::assertStringContainsString(', ["pad" => 1]);', $php);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function runtimeErrorProvider(): iterable
    {
        yield 'empty body' => ["<p>\n{% d2 %}\n  \n{% endd2 %}", 'must not be empty'];
        yield 'unknown option' => ["<p>\n{% d2 with { them: 1 } %}a{% endd2 %}", 'Unknown D2 option(s) "them"'];
    }

    #[DataProvider('runtimeErrorProvider')]
    public function testInvalidDiagramRaisesRuntimeErrorAtTheTagLine(string $template, string $message): void
    {
        try {
            self::twig(new SpyRenderer(), ['page' => $template])->render('page');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(2, $e->getTemplateLine());
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
            self::assertStringContainsString($message, $e->getPrevious()->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function syntaxErrorProvider(): iterable
    {
        yield 'missing end tag' => ['{% d2 %}a -> b'];
        yield 'unexpected token' => ['{% d2 foo %}a{% endd2 %}'];
        yield 'with without expression' => ['{% d2 with %}a{% endd2 %}'];
    }

    #[DataProvider('syntaxErrorProvider')]
    public function testInvalidSyntaxIsRejected(string $template): void
    {
        $this->expectException(SyntaxError::class);

        self::twig(new SpyRenderer(), ['page' => $template])->render('page');
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(SpyRenderer $renderer, array $templates, RenderOptions $defaults = new RenderOptions(), bool $useYield = false): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true, 'use_yield' => $useYield]);
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime($renderer, $defaults),
        ]));

        return $twig;
    }
}
