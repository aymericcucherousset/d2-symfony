<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Profiler;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Records the diagrams rendered during the current request, for the Web Profiler.
 *
 * {@see TraceableRenderer} opens a trace around each render and
 * {@see TraceableBackendRenderer} adds the renderers (binary, Kroki) tried to
 * produce it; a render that reaches no renderer was served from the cache.
 *
 * @phpstan-type Attempt array{renderer: string, status: 'success'|'unavailable'|'error', message: string|null}
 * @phpstan-type Trace array{
 *     source: string,
 *     options: array<string, int|string|true>,
 *     svg: string|null,
 *     error: array{class: class-string, message: string}|null,
 *     duration: float,
 *     cache: 'hit'|'miss'|null,
 *     renderer: string|null,
 *     attempts: list<Attempt>,
 * }
 *
 * @internal
 */
final class RenderTracer implements ResetInterface
{
    /** @var list<Trace> */
    private array $traces = [];

    /** @var list<array{source: string, options: array<string, int|string|true>, start: float, attempts: list<Attempt>}> */
    private array $stack = [];

    public function start(Diagram $diagram): void
    {
        $this->stack[] = [
            'source' => $diagram->source,
            'options' => $diagram->options->toArray(),
            'start' => hrtime(true) / 1e6,
            'attempts' => [],
        ];
    }

    /**
     * Records that a renderer was tried for the render in progress.
     *
     * @param 'success'|'unavailable'|'error' $status
     */
    public function attempt(string $renderer, string $status, ?\Throwable $error = null): void
    {
        $current = array_key_last($this->stack);
        if (null === $current) {
            // The renderer was used directly, outside of the main d2.renderer service.
            return;
        }

        $this->stack[$current]['attempts'][] = ['renderer' => $renderer, 'status' => $status, 'message' => $error?->getMessage()];
    }

    /**
     * Ends the render in progress.
     *
     * @param bool $cached whether the render went through the cache, to tell hits from misses
     */
    public function finish(?string $svg, ?\Throwable $error, bool $cached): void
    {
        $frame = array_pop($this->stack);
        if (null === $frame) {
            return;
        }

        $renderer = null;
        foreach ($frame['attempts'] as $attempt) {
            if ('unavailable' !== $attempt['status']) {
                $renderer = $attempt['renderer'];
            }
        }

        $this->traces[] = [
            'source' => $frame['source'],
            'options' => $frame['options'],
            'svg' => $svg,
            'error' => null === $error ? null : ['class' => $error::class, 'message' => $error->getMessage()],
            'duration' => hrtime(true) / 1e6 - $frame['start'],
            'cache' => $cached ? ([] === $frame['attempts'] ? 'hit' : 'miss') : null,
            'renderer' => $renderer,
            'attempts' => $frame['attempts'],
        ];
    }

    /**
     * @return list<Trace>
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    public function reset(): void
    {
        $this->traces = [];
        $this->stack = [];
    }
}
