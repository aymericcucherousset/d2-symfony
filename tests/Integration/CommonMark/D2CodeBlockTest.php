<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\CommonMark;

use Aymericcucherousset\D2Symfony\CommonMark\D2CommonMarkExtension;
use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Tests\Integration\RequiresD2Binary;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;

final class D2CodeBlockTest extends TestCase
{
    use RequiresD2Binary;

    public function testCodeBlockIsRenderedToInlineSvgWithTheRealBinary(): void
    {
        self::requireD2Binary();

        $html = self::converter()->convert("# Flow\n\n```d2\nuser -> app: HTTPS\n```\n\nText.\n")->getContent();

        self::assertStringStartsWith("<h1>Flow</h1>\n<svg", $html);
        self::assertStringNotContainsString('<?xml', $html);
        self::assertStringContainsString('HTTPS', $html);
        self::assertStringEndsWith("</svg>\n<p>Text.</p>\n", $html);
    }

    public function testSyntaxErrorPointsToTheMarkdownLine(): void
    {
        self::requireD2Binary();

        try {
            self::converter()->convert("# Architecture\n\nIntro.\n\n```d2\nuser -> app\napp: {\n  db\n```\n");
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException $e) {
            // The fence is on line 5; d2 reports the unclosed map of line 7.
            self::assertNotNull($e->getD2Line());
            self::assertStringEndsWith(\sprintf('(at line %d of the Markdown document)', 5 + $e->getD2Line()), $e->getMessage());
        }
    }

    private static function converter(): CommonMarkConverter
    {
        $converter = new CommonMarkConverter();
        $converter->getEnvironment()->addExtension(new D2CommonMarkExtension(new BinaryRenderer()));

        return $converter;
    }
}
