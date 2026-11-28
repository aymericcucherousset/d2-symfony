<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\CommonMark;

use Aymericcucherousset\D2Symfony\CommonMark\D2CodeBlockRenderer;
use Aymericcucherousset\D2Symfony\CommonMark\D2CommonMarkExtension;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Renderer\HtmlRenderer;
use PHPUnit\Framework\TestCase;

final class D2CommonMarkExtensionTest extends TestCase
{
    public function testD2CodeBlockIsRenderedToInlineSvg(): void
    {
        $spy = new SpyRenderer('<svg><text>a &amp; b</text></svg>');

        $html = self::convert($spy, "# Architecture\n\n```d2\na -> b\nb -> c\n```\n\nThe end.\n");

        self::assertSame("<h1>Architecture</h1>\n<svg><text>a &amp; b</text></svg>\n<p>The end.</p>\n", $html);
        self::assertCount(1, $spy->diagrams);
        self::assertSame("a -> b\nb -> c\n", $spy->diagrams[0]->source);
    }

    public function testOptionsAreAppliedToEveryDiagram(): void
    {
        $spy = new SpyRenderer();
        $options = new RenderOptions(theme: 200, sketch: true);

        self::convert($spy, "```d2\na\n```\n\n```d2\nb\n```\n", $options);

        self::assertCount(2, $spy->diagrams);
        self::assertSame($options, $spy->diagrams[0]->options);
        self::assertSame($options, $spy->diagrams[1]->options);
    }

    public function testOtherCodeBlocksAreLeftToTheDefaultRenderer(): void
    {
        $spy = new SpyRenderer();

        $html = self::convert($spy, "```php\necho 1;\n```\n\n```\nplain\n```\n\n    indented d2\n\n`d2` inline\n");

        self::assertSame(
            "<pre><code class=\"language-php\">echo 1;\n</code></pre>\n<pre><code>plain\n</code></pre>\n<pre><code>indented d2\n</code></pre>\n<p><code>d2</code> inline</p>\n",
            $html,
        );
        self::assertSame([], $spy->diagrams);
    }

    public function testLanguageIsCaseInsensitiveAndExtraInfoWordsAreIgnored(): void
    {
        $spy = new SpyRenderer();

        $html = self::convert($spy, "~~~D2 title=\"Flow\"\na -> b\n~~~\n");

        self::assertSame("<svg>rendered</svg>\n", $html);
        self::assertSame("a -> b\n", $spy->diagrams[0]->source);
    }

    public function testLanguagesStartingWithD2AreNotDiagrams(): void
    {
        $spy = new SpyRenderer();

        $html = self::convert($spy, "```d2lang\na -> b\n```\n");

        self::assertSame("<pre><code class=\"language-d2lang\">a -&gt; b\n</code></pre>\n", $html);
        self::assertSame([], $spy->diagrams);
    }

    public function testEmptyD2BlockIsRenderedAsCode(): void
    {
        $spy = new SpyRenderer();

        $html = self::convert($spy, "```d2\n   \n```\n");

        self::assertSame("<pre><code class=\"language-d2\">   \n</code></pre>\n", $html);
        self::assertSame([], $spy->diagrams);
    }

    public function testXmlDeclarationAndTrailingWhitespaceAreStripped(): void
    {
        $spy = new SpyRenderer("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<svg></svg>\n");

        self::assertSame("<svg></svg>\n", self::convert($spy, "```d2\na\n```\n"));
    }

    public function testWorksWithGithubFlavoredMarkdown(): void
    {
        $converter = new GithubFlavoredMarkdownConverter();
        $converter->getEnvironment()->addExtension(new D2CommonMarkExtension(new SpyRenderer()));

        self::assertSame("<svg>rendered</svg>\n<p><del>done</del></p>\n", (string) $converter->convert("```d2\na\n```\n\n~~done~~\n"));
    }

    public function testSyntaxErrorGivesTheMarkdownLine(): void
    {
        $renderer = new FailingRenderer("err: failed to compile -: -:2:3: unexpected text\n");

        try {
            // The fence is on line 3, so D2 line 2 is Markdown line 5.
            self::convert($renderer, "# Title\n\n```d2\na -> b\nc -> \n```\n");
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException $e) {
            self::assertSame('D2 syntax error at line 2, column 3: unexpected text (at line 5 of the Markdown document)', $e->getMessage());
            self::assertSame(2, $e->getD2Line());
            self::assertSame("err: failed to compile -: -:2:3: unexpected text\n", $e->getD2Error());
            self::assertInstanceOf(RenderException::class, $e->getPrevious());
        }
    }

    public function testErrorWithoutD2LineGivesTheCodeBlockLine(): void
    {
        $this->expectException(RenderException::class);
        $this->expectExceptionMessage('D2 rendering failed: layout timed out (in the d2 code block at line 3 of the Markdown document)');

        self::convert(new FailingRenderer('layout timed out'), "Intro\n\n```d2\na -> b\n```\n");
    }

    public function testOtherFormatsThanSvgAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Markdown d2 code blocks are rendered to inline SVG and cannot be rendered to PNG.');

        new D2CommonMarkExtension(new SpyRenderer(), new RenderOptions(format: Format::Png));
    }

    public function testRendererIgnoresOtherNodes(): void
    {
        $renderer = new D2CodeBlockRenderer(new SpyRenderer());
        $html = new HtmlRenderer((new CommonMarkConverter())->getEnvironment());

        self::assertNull($renderer->render(new Paragraph(), $html));
        self::assertNull($renderer->render(new FencedCode(3, '`', 0), $html));
    }

    private static function convert(Renderer $renderer, string $markdown, RenderOptions $options = new RenderOptions()): string
    {
        $converter = new CommonMarkConverter();
        $converter->getEnvironment()->addExtension(new D2CommonMarkExtension($renderer, $options));

        return (string) $converter->convert($markdown);
    }
}
