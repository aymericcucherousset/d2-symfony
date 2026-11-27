<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\RendererUnavailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;

/**
 * Renders D2 diagrams to SVG.
 *
 * This is the central port of the library: every rendering strategy (local
 * binary, Kroki, cache, profiler…) implements it, and decorators wrap it.
 *
 * Implementations must:
 *  - return the same SVG for diagrams with the same fingerprint, so results can be cached;
 *  - only throw exceptions implementing {@see D2Exception}.
 */
interface Renderer
{
    /**
     * Renders a D2 diagram to an SVG document.
     *
     * @return non-empty-string the SVG markup
     *
     * @throws RenderException              when D2 rejects the source or rendering fails
     * @throws BinaryNotFoundException      when the renderer relies on a d2 binary that cannot be executed
     * @throws RendererUnavailableException when the renderer cannot run at all (missing binary, unreachable server…)
     */
    public function render(Diagram $diagram): string;
}
