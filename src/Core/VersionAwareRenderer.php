<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;

/**
 * A renderer able to tell which version of the rendering engine it uses.
 *
 * The version is part of cache keys, so that cached diagrams are rendered
 * again after the engine (e.g. the d2 binary) is upgraded.
 */
interface VersionAwareRenderer extends Renderer
{
    /**
     * Returns the version of the rendering engine, e.g. "v0.9.0".
     *
     * @throws D2Exception when the version cannot be determined
     */
    public function getVersion(): string;
}
