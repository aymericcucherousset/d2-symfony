<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\RendererUnavailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Exception\UnsupportedFormatException;

/**
 * Renders D2 diagrams to SVG, or to the format of their options (PNG, PDF).
 *
 * This is the central port of the library: every rendering strategy (local
 * binary, Kroki, cache, profiler…) implements it, and decorators wrap it.
 *
 * Implementations must:
 *  - return the same document for diagrams with the same fingerprint, so results can be cached;
 *  - render to the format of {@see RenderOptions::$format}, or throw an
 *    {@see UnsupportedFormatException} when they cannot;
 *  - only throw exceptions implementing {@see D2Exception}.
 */
interface Renderer
{
    /**
     * Renders a D2 diagram to a document in the format of its options.
     *
     * @return non-empty-string the SVG markup, or the binary PNG or PDF document
     *
     * @throws RenderException              when D2 rejects the source or rendering fails
     * @throws BinaryNotFoundException      when the renderer relies on a d2 binary that cannot be executed
     * @throws RendererUnavailableException when the renderer cannot run at all (missing binary, unreachable server…)
     *                                      or cannot produce the format ({@see UnsupportedFormatException})
     */
    public function render(Diagram $diagram): string;
}
