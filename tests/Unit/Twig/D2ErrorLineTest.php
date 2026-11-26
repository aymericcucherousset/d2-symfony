<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * D2 errors must point to the template line holding the faulty D2 line.
 */
final class D2ErrorLineTest extends TestCase
{
    private const D2_ERROR_ON_LINE_3 = "err: failed to compile -: -:3:4: maps must be terminated with }\n";

    /**
     * @return iterable<string, array{string, int, 2?: bool}>
     */
    public static function tagProvider(): iterable
    {
        yield 'body on the next line' => ["<html>\n{% d2 %}\na\nb\nc: {\n{% endd2 %}", 5];
        yield 'body on the tag line' => ["<html>\n{% d2 %}a\nb\nc: {\n{% endd2 %}", 4];
        yield 'whitespace control' => ["<html>\n{%- d2 -%}\n\na\nb\nc: {\n{% endd2 %}", 6];
        yield 'multi-line options' => ["<html>\n{% d2 with {\n  sketch: true\n} %}\na\nb\nc: {\n{% endd2 %}", 7];
        yield 'yield mode' => ["<html>\n{% d2 %}\na\nb\nc: {\n{% endd2 %}", 5, true];
    }

    #[DataProvider('tagProvider')]
    public function testTagErrorPointsToTheFaultyTemplateLine(string $template, int $expectedLine, bool $useYield = false): void
    {
        $twig = self::twig(new FailingRenderer(self::D2_ERROR_ON_LINE_3), ['page.html.twig' => $template], $useYield);

        try {
            $twig->render('page.html.twig');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame($expectedLine, $e->getTemplateLine());
            self::assertSame('page.html.twig', $e->getSourceContext()?->getName());
            self::assertSame('D2 syntax error at line 3, column 4: maps must be terminated with } in "page.html.twig" at line '.$expectedLine, $e->getMessage());
            self::assertInstanceOf(RenderException::class, $e->getPrevious());
        }
    }

    public function testTagErrorWithoutLocationPointsToTheTag(): void
    {
        $twig = self::twig(new FailingRenderer("err: layout engine crashed\n"), ['page' => "<html>\n{% d2 %}\na\n{% endd2 %}"]);

        try {
            $twig->render('page');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(2, $e->getTemplateLine());
            self::assertInstanceOf(RenderException::class, $e->getPrevious());
        }
    }

    public function testFilterErrorPointsToTheFilterWithAReadableMessage(): void
    {
        $twig = self::twig(new FailingRenderer(self::D2_ERROR_ON_LINE_3), ['page' => "<html>\n\n{{ source|d2 }}"]);

        try {
            $twig->render('page', ['source' => "a\nb\nc: {"]);
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(3, $e->getTemplateLine());
            self::assertStringContainsString('D2 syntax error at line 3, column 4: maps must be terminated with }', $e->getMessage());
        }
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(Renderer $renderer, array $templates, bool $useYield = false): Environment
    {
        $twig = new Environment(new ArrayLoader($templates), ['use_yield' => $useYield]);
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime($renderer),
        ]));

        return $twig;
    }
}
