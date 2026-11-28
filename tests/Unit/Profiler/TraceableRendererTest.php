<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Profiler;

use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\ChainRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Profiler\D2DataCollector;
use Aymericcucherousset\D2Symfony\Profiler\RenderTracer;
use Aymericcucherousset\D2Symfony\Profiler\TraceableBackendRenderer;
use Aymericcucherousset\D2Symfony\Profiler\TraceableRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\UnavailableRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\VersionedSpyRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Stopwatch\Stopwatch;

final class TraceableRendererTest extends TestCase
{
    private RenderTracer $tracer;
    private D2DataCollector $collector;

    protected function setUp(): void
    {
        $this->tracer = new RenderTracer();
        $this->collector = new D2DataCollector($this->tracer);
    }

    public function testRenderIsTracedWithItsSourceOptionsAndSvg(): void
    {
        $renderer = new TraceableRenderer(new TraceableBackendRenderer(new SpyRenderer('<svg>a</svg>'), $this->tracer, 'binary'), $this->tracer);

        $svg = $renderer->render(new Diagram('a -> b', new RenderOptions(theme: 3, sketch: true)));
        $this->collector->lateCollect();

        self::assertSame('<svg>a</svg>', $svg);
        self::assertSame(1, $this->collector->getRenderCount());
        self::assertSame(0, $this->collector->getErrorCount());
        self::assertSame(['binary' => 1], $this->collector->getRenderers());

        $render = $this->collector->getRenders()[0];
        self::assertSame('a -> b', $render['source']);
        self::assertSame(['theme' => 3, 'sketch' => true], $render['options']);
        self::assertSame('data:image/svg+xml;base64,'.base64_encode('<svg>a</svg>'), $render['svg_data_uri']);
        self::assertSame(12, $render['svg_size']);
        self::assertNull($render['cache']);
        self::assertSame('binary', $render['renderer']);
        self::assertSame([['renderer' => 'binary', 'status' => 'success', 'message' => null]], $render['attempts']);
        self::assertGreaterThanOrEqual(0.0, $render['duration']);
        self::assertSame($render['duration'], $this->collector->getDuration());
    }

    public function testPngPreviewUsesItsMediaTypeAndPdfHasNoPreview(): void
    {
        $renderer = new TraceableRenderer(new SpyRenderer("\x89PNG"), $this->tracer);

        $renderer->render(new Diagram('a', new RenderOptions(format: Format::Png)));
        $renderer->render(new Diagram('a', new RenderOptions(format: Format::Pdf)));
        $this->collector->lateCollect();

        [$png, $pdf] = $this->collector->getRenders();
        self::assertSame('data:image/png;base64,'.base64_encode("\x89PNG"), $png['svg_data_uri']);
        self::assertSame(['format' => 'pdf'], $pdf['options']);
        self::assertNull($pdf['svg_data_uri']);
        self::assertSame(4, $pdf['svg_size']);
    }

    public function testCacheHitsAndMissesAreReported(): void
    {
        $backend = new TraceableBackendRenderer(new VersionedSpyRenderer(), $this->tracer, 'kroki');
        $renderer = new TraceableRenderer(new CachedRenderer($backend, new ArrayAdapter()), $this->tracer, cached: true);

        $renderer->render(new Diagram('a -> b'));
        $renderer->render(new Diagram('a -> b'));
        $this->collector->lateCollect();

        self::assertSame(1, $this->collector->getCacheMisses());
        self::assertSame(1, $this->collector->getCacheHits());
        self::assertSame(['kroki' => 1], $this->collector->getRenderers());
        self::assertSame(['miss', 'hit'], array_column($this->collector->getRenders(), 'cache'));
        self::assertNull($this->collector->getRenders()[1]['renderer']);
    }

    public function testChainFallbackIsReported(): void
    {
        $chain = new ChainRenderer([
            new TraceableBackendRenderer(new UnavailableRenderer('missing-d2'), $this->tracer, 'binary'),
            new TraceableBackendRenderer(new SpyRenderer(), $this->tracer, 'kroki'),
        ]);

        (new TraceableRenderer($chain, $this->tracer))->render(new Diagram('a -> b'));
        $this->collector->lateCollect();

        $render = $this->collector->getRenders()[0];
        self::assertSame('kroki', $render['renderer']);
        self::assertSame(['binary', 'kroki'], array_column($render['attempts'], 'renderer'));
        self::assertSame(['unavailable', 'success'], array_column($render['attempts'], 'status'));
        self::assertStringContainsString('missing-d2', (string) $render['attempts'][0]['message']);
    }

    public function testFailedRenderIsReportedAndRethrown(): void
    {
        $renderer = new TraceableRenderer(new TraceableBackendRenderer(new FailingRenderer('-:1:3: unexpected text'), $this->tracer, 'binary'), $this->tracer);

        try {
            $renderer->render(new Diagram('a b c'));
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException) {
        }
        $this->collector->lateCollect();

        self::assertSame(1, $this->collector->getErrorCount());
        $render = $this->collector->getRenders()[0];
        self::assertSame(RenderException::class, $render['error']['class'] ?? null);
        self::assertNull($render['svg_data_uri']);
        self::assertSame('binary', $render['renderer']);
        self::assertSame('error', $render['attempts'][0]['status']);
    }

    public function testRenderIsTimedWithTheStopwatch(): void
    {
        $stopwatch = new Stopwatch();
        (new TraceableRenderer(new SpyRenderer(), $this->tracer, stopwatch: $stopwatch))->render(new Diagram('a -> b'));

        self::assertCount(1, $stopwatch->getEvent('d2.render')->getPeriods());
        self::assertSame('d2', $stopwatch->getEvent('d2.render')->getCategory());
    }

    public function testVersionIsForwarded(): void
    {
        $versioned = new VersionedSpyRenderer('v0.9.0');

        self::assertSame('v0.9.0', (new TraceableRenderer($versioned, $this->tracer))->getVersion());
        self::assertSame('v0.9.0', (new TraceableBackendRenderer($versioned, $this->tracer, 'binary'))->getVersion());
        self::assertSame('', (new TraceableRenderer(new SpyRenderer(), $this->tracer))->getVersion());
        self::assertSame('', (new TraceableBackendRenderer(new SpyRenderer(), $this->tracer, 'binary'))->getVersion());
    }

    public function testBackendUsedOutsideOfATracedRenderIsIgnored(): void
    {
        (new TraceableBackendRenderer(new SpyRenderer(), $this->tracer, 'binary'))->render(new Diagram('a -> b'));

        self::assertSame([], $this->tracer->getTraces());
    }

    public function testResetClearsCollectedData(): void
    {
        (new TraceableRenderer(new SpyRenderer(), $this->tracer))->render(new Diagram('a -> b'));
        $this->collector->lateCollect();

        $this->collector->reset();

        self::assertSame(0, $this->collector->getRenderCount());
        self::assertSame([], $this->tracer->getTraces());
        self::assertSame('d2', $this->collector->getName());
    }
}
