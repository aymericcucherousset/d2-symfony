<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Twig;

use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2FileLoader;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Markup;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class D2ExtensionTest extends TestCase
{
    private const DIAGRAMS = __DIR__.'/../../Fixtures/diagrams';

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

    public function testFileFunctionRendersFileRelativeToRootDirectory(): void
    {
        $spy = new SpyRenderer('<?xml version="1.0"?><svg>file</svg>');
        $twig = self::twig($spy, ['page' => "<div>{{ d2_file('architecture.d2', { sketch: true }) }}</div>"], new RenderOptions(theme: 1), self::DIAGRAMS);

        self::assertSame('<div><svg>file</svg></div>', $twig->render('page'));
        self::assertSame("user -> app: HTTPS\napp -> db\n", $spy->diagrams[0]->source);
        self::assertEquals(new RenderOptions(theme: 1, sketch: true), $spy->diagrams[0]->options);
    }

    public function testFileFunctionAcceptsVariablePath(): void
    {
        $spy = new SpyRenderer();

        self::twig($spy, ['page' => '{{ d2_file(path) }}'], diagramsDir: self::DIAGRAMS)->render('page', ['path' => 'nested/flow.d2']);

        self::assertSame("a -> b\n", $spy->diagrams[0]->source);
    }

    public function testFileFunctionRejectsPathTraversal(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ d2_file('../outside.d2') }}", 'is outside of', self::DIAGRAMS);
    }

    public function testFileFunctionReportsMissingFile(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ d2_file('missing.d2') }}", 'The D2 file "missing.d2" does not exist', self::DIAGRAMS);
    }

    public function testFileFunctionRequiresRootDirectory(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ d2_file('architecture.d2') }}", 'requires a root directory');
    }

    public function testFileFunctionErrorNamesTheFileAndTemplateLine(): void
    {
        $twig = self::twig(new FailingRenderer('err: -:2:3: unexpected token'), ['page' => "<p>\n{{ d2_file('architecture.d2') }}"], diagramsDir: self::DIAGRAMS);

        try {
            $twig->render('page');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(2, $e->getTemplateLine());
            self::assertSame('page', $e->getSourceContext()?->getName());
            self::assertStringContainsString('D2 syntax error at line 2, column 3: unexpected token (in D2 file "architecture.d2")', $e->getMessage());
            self::assertInstanceOf(RenderException::class, $e->getPrevious());
        }
    }

    public function testEditedFileIsRenderedAgainDespiteCache(): void
    {
        $dir = sys_get_temp_dir().'/d2-file-cache-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $file = $dir.'/live.d2';

        try {
            $spy = new SpyRenderer();
            $twig = new Environment(new ArrayLoader(['page' => "{{ d2_file('live.d2') }}"]));
            $twig->addExtension(new D2Extension());
            $twig->addRuntimeLoader(new FactoryRuntimeLoader([
                D2Runtime::class => static fn (): D2Runtime => new D2Runtime(new CachedRenderer($spy, new ArrayAdapter()), new RenderOptions(), new D2FileLoader($dir)),
            ]));

            file_put_contents($file, 'a -> b');
            $twig->render('page');
            $twig->render('page');
            self::assertSame(['a -> b'], self::sources($spy), 'An unchanged file is served from the cache.');

            file_put_contents($file, 'a -> c');
            $twig->render('page');
            self::assertSame(['a -> b', 'a -> c'], self::sources($spy), 'An edited file is rendered again.');
        } finally {
            (new Filesystem())->remove($dir);
        }
    }

    public function testDataUriFilterRendersPngByDefault(): void
    {
        $spy = new SpyRenderer("\x89PNG-bytes");

        $output = self::twig($spy, ['page' => '<img src="{{ src|d2_data_uri }}">'], new RenderOptions(theme: 1))->render('page', ['src' => 'a -> b']);

        self::assertSame('<img src="data:image/png;base64,'.base64_encode("\x89PNG-bytes").'">', $output);
        self::assertEquals(new RenderOptions(theme: 1, format: Format::Png), $spy->diagrams[0]->options);
    }

    public function testDataUriFilterAcceptsOptionsAndOtherFormats(): void
    {
        $spy = new SpyRenderer('<svg/>');

        $output = self::twig($spy, ['page' => "{{ 'a'|d2_data_uri({ format: 'svg', sketch: true }) }}|{{ 'a'|d2_data_uri({ format: 'pdf' }) }}"])->render('page');

        self::assertSame('data:image/svg+xml;base64,'.base64_encode('<svg/>').'|data:application/pdf;base64,'.base64_encode('<svg/>'), $output);
        self::assertEquals(new RenderOptions(sketch: true), $spy->diagrams[0]->options);
        self::assertSame(Format::Pdf, $spy->diagrams[1]->options->format);
    }

    public function testDataUriFilterTreatsANullFormatAsPng(): void
    {
        $spy = new SpyRenderer("\x89PNG-bytes");

        $output = self::twig($spy, ['page' => "{{ 'a'|d2_data_uri({ format: null }) }}"])->render('page');

        self::assertStringStartsWith('data:image/png;base64,', $output);
        self::assertSame(Format::Png, $spy->diagrams[0]->options->format);
    }

    public function testD2FilterAndTagRejectBinaryFormats(): void
    {
        self::assertRuntimeErrorCausedByInvalidArgument("{{ 'a'|d2({ format: 'png' }) }}", 'cannot render to PNG; use the "d2_data_uri" filter instead');
        self::assertRuntimeErrorCausedByInvalidArgument("{% d2 with { format: 'pdf' } %}a -> b{% endd2 %}", 'cannot render to PDF');
    }

    private static function assertRuntimeErrorCausedByInvalidArgument(string $template, string $message, ?string $diagramsDir = null): void
    {
        try {
            self::twig(new SpyRenderer(), ['page' => $template], diagramsDir: $diagramsDir)->render('page');
            self::fail('A Twig RuntimeError was expected.');
        } catch (RuntimeError $e) {
            self::assertSame(1, $e->getTemplateLine());
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
            self::assertStringContainsString($message, $e->getPrevious()->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private static function sources(SpyRenderer $spy): array
    {
        return array_map(static fn (Diagram $diagram): string => $diagram->source, $spy->diagrams);
    }

    /**
     * @param array<string, string> $templates
     */
    private static function twig(Renderer $renderer, array $templates, RenderOptions $defaults = new RenderOptions(), ?string $diagramsDir = null): Environment
    {
        $files = null === $diagramsDir ? null : new D2FileLoader($diagramsDir);
        $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
        $twig->addExtension(new D2Extension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            D2Runtime::class => static fn (): D2Runtime => new D2Runtime($renderer, $defaults, $files),
        ]));

        return $twig;
    }
}
