<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Profiler;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\VersionAwareRenderer;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Decorates the main renderer to time each render and report it to the Web Profiler.
 *
 * Only registered when kernel.debug is enabled and the profiler is available.
 *
 * @internal
 */
final readonly class TraceableRenderer implements VersionAwareRenderer
{
    /**
     * @param bool $cached whether the decorated renderer caches results, to report cache hits and misses
     */
    public function __construct(
        private Renderer $inner,
        private RenderTracer $tracer,
        private bool $cached = false,
        private ?Stopwatch $stopwatch = null,
    ) {
    }

    public function render(Diagram $diagram): string
    {
        $this->tracer->start($diagram);
        $event = $this->stopwatch?->start('d2.render', 'd2');

        try {
            $svg = $this->inner->render($diagram);
        } catch (\Throwable $e) {
            $this->tracer->finish(null, $e, $this->cached);

            throw $e;
        } finally {
            $event?->stop();
        }

        $this->tracer->finish($svg, null, $this->cached);

        return $svg;
    }

    public function getVersion(): string
    {
        return $this->inner instanceof VersionAwareRenderer ? $this->inner->getVersion() : '';
    }
}
