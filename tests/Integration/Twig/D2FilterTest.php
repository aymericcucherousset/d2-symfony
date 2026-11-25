<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\Twig;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Tests\Integration\RequiresD2Binary;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class D2FilterTest extends TestCase
{
    use RequiresD2Binary;

    public function testFilterRendersInlineSvgWithTheRealBinary(): void
    {
        self::requireD2Binary();

        $twig = new Environment(new ArrayLoader(['page' => "{{ 'a -> b'|d2({ sketch: true }) }}"]));
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime(new BinaryRenderer()),
        ]));

        $output = $twig->render('page');

        self::assertStringStartsWith('<svg', $output);
        self::assertStringNotContainsString('<?xml', $output);
        self::assertStringEndsWith('</svg>', trim($output));
    }
}
