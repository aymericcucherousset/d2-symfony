<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Profiler;

use Aymericcucherousset\D2Symfony\Core\Format;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * Collects the diagrams rendered during a request for the Web Profiler "D2" panel.
 *
 * @phpstan-type Render array{
 *     source: string,
 *     options: array<string, int|float|string|true>,
 *     svg_data_uri: string|null,
 *     svg_size: int,
 *     error: array{class: class-string, message: string}|null,
 *     duration: float,
 *     cache: 'hit'|'miss'|null,
 *     renderer: string|null,
 *     attempts: list<array{renderer: string, status: 'success'|'unavailable'|'error', message: string|null}>,
 * }
 *
 * @internal
 */
final class D2DataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(private readonly RenderTracer $tracer)
    {
        $this->data = ['renders' => []];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Diagrams may still be rendered while the response is sent (streamed templates): see lateCollect().
    }

    public function lateCollect(): void
    {
        $renders = [];
        foreach ($this->tracer->getTraces() as $trace) {
            $document = $trace['svg'];
            unset($trace['svg']);
            $format = Format::tryFrom((string) ($trace['options']['format'] ?? Format::Svg->value)) ?? Format::Svg;

            // Shown in an <img>, so the SVG styles and scripts cannot leak into the profiler page;
            // browsers do not display PDF documents in an <img>, so they get no preview.
            $renders[] = $trace + [
                'svg_data_uri' => null === $document || Format::Pdf === $format ? null : 'data:'.$format->mimeType().';base64,'.base64_encode($document),
                'svg_size' => null === $document ? 0 : \strlen($document),
            ];
        }

        $this->data = ['renders' => $renders];
    }

    public function getName(): string
    {
        return 'd2';
    }

    public function reset(): void
    {
        $this->data = ['renders' => []];
        $this->tracer->reset();
    }

    /**
     * @return list<Render>
     */
    public function getRenders(): array
    {
        /** @var list<Render> $renders */
        $renders = $this->data['renders'] ?? [];

        return $renders;
    }

    public function getRenderCount(): int
    {
        return \count($this->getRenders());
    }

    /**
     * Total rendering time, in milliseconds.
     */
    public function getDuration(): float
    {
        return array_sum(array_column($this->getRenders(), 'duration'));
    }

    public function getCacheHits(): int
    {
        return \count(array_filter($this->getRenders(), static fn (array $render): bool => 'hit' === $render['cache']));
    }

    public function getCacheMisses(): int
    {
        return \count(array_filter($this->getRenders(), static fn (array $render): bool => 'miss' === $render['cache']));
    }

    public function getErrorCount(): int
    {
        return \count(array_filter($this->getRenders(), static fn (array $render): bool => null !== $render['error']));
    }

    /**
     * Number of diagrams produced by each renderer (binary, kroki), cache hits excluded.
     *
     * @return array<string, int>
     */
    public function getRenderers(): array
    {
        $renderers = [];
        foreach ($this->getRenders() as $render) {
            if (null !== $render['renderer']) {
                $renderers[$render['renderer']] = ($renderers[$render['renderer']] ?? 0) + 1;
            }
        }

        return $renderers;
    }
}
