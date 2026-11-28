<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Profiler;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\RendererUnavailableException;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\VersionAwareRenderer;

/**
 * Decorates a concrete renderer (binary, Kroki) to report to the Web Profiler which one produced a diagram.
 *
 * Only registered when kernel.debug is enabled and the profiler is available.
 *
 * @internal
 */
final readonly class TraceableBackendRenderer implements VersionAwareRenderer
{
    public function __construct(
        private Renderer $inner,
        private RenderTracer $tracer,
        private string $name,
    ) {
    }

    public function render(Diagram $diagram): string
    {
        try {
            $svg = $this->inner->render($diagram);
        } catch (RendererUnavailableException $e) {
            $this->tracer->attempt($this->name, 'unavailable', $e);

            throw $e;
        } catch (\Throwable $e) {
            $this->tracer->attempt($this->name, 'error', $e);

            throw $e;
        }

        $this->tracer->attempt($this->name, 'success');

        return $svg;
    }

    public function getVersion(): string
    {
        return $this->inner instanceof VersionAwareRenderer ? $this->inner->getVersion() : '';
    }
}
