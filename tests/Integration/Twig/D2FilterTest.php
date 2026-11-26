<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\Twig;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Tests\Integration\RequiresD2Binary;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class D2FilterTest extends TestCase
{
    use RequiresD2Binary;

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateProvider(): iterable
    {
        yield 'filter' => ["{{ 'a -> b'|d2({ sketch: true }) }}"];
        yield 'tag' => ["{% d2 with { sketch: true } %}\na -> {{ 'b' }}\n{% endd2 %}"];
    }

    #[DataProvider('templateProvider')]
    public function testTemplateRendersInlineSvgWithTheRealBinary(string $template): void
    {
        self::requireD2Binary();

        $twig = new Environment(new ArrayLoader(['page' => $template]));
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime(new BinaryRenderer()),
        ]));

        $output = $twig->render('page');

        self::assertStringStartsWith('<svg', $output);
        self::assertStringNotContainsString('<?xml', $output);
        self::assertStringEndsWith('</svg>', trim($output));
    }

    public function testTagSyntaxErrorPointsToTheTemplateLine(): void
    {
        self::requireD2Binary();

        $template = "<h1>Architecture</h1>\n{% d2 %}\nuser -> app\napp: {\n  db\n{% endd2 %}";
        $twig = new Environment(new ArrayLoader(['page.html.twig' => $template]));
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime(new BinaryRenderer()),
        ]));

        try {
            $twig->render('page.html.twig');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            // D2 reports line 2 ("app: {"), which is line 4 of the template.
            self::assertSame(4, $e->getTemplateLine());
            self::assertStringContainsString('maps must be terminated with }', $e->getMessage());
        }
    }
}
