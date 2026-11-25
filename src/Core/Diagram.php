<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;

/**
 * Immutable D2 diagram to render: its source and render options.
 */
final readonly class Diagram
{
    public function __construct(
        public string $source,
        public RenderOptions $options = new RenderOptions(),
    ) {
        if ('' === trim($source)) {
            throw new InvalidArgumentException('The D2 source must not be empty.');
        }
    }

    public function withSource(string $source): self
    {
        return new self($source, $this->options);
    }

    public function withOptions(RenderOptions $options): self
    {
        return new self($this->source, $options);
    }

    /**
     * Returns a stable hash of the source and options, suitable as part of a cache key.
     */
    public function fingerprint(): string
    {
        return hash('sha256', $this->source."\0".$this->options->fingerprint());
    }
}
