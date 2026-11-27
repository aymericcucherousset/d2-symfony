<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when a renderer cannot run at all (missing binary, unreachable server…),
 * as opposed to D2 rejecting the diagram: another renderer may succeed.
 *
 * {@see \Aymericcucherousset\D2Symfony\Core\ChainRenderer} falls back to the next renderer on these errors only.
 */
interface RendererUnavailableException extends D2Exception
{
}
