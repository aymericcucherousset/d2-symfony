<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

use Aymericcucherousset\D2Symfony\Core\Format;

/**
 * Thrown when a renderer cannot produce the requested output format (e.g. PNG with Kroki).
 *
 * Another renderer may support it, so a {@see \Aymericcucherousset\D2Symfony\Core\ChainRenderer}
 * falls back to the next renderer.
 */
final class UnsupportedFormatException extends \RuntimeException implements RendererUnavailableException
{
    public function __construct(
        private readonly Format $format,
        string $renderer,
    ) {
        parent::__construct(\sprintf('The %s renderer cannot render diagrams to %s.', $renderer, strtoupper($format->value)));
    }

    public function getFormat(): Format
    {
        return $this->format;
    }
}
