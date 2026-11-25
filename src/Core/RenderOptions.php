<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

/**
 * Immutable set of options passed to the d2 renderer.
 *
 * A null value (or false for sketch) means "use the d2 default": the
 * corresponding flag is not emitted.
 */
final readonly class RenderOptions
{
    private const LAYOUT_PATTERN = '/^[a-z0-9_-]+$/';

    public function __construct(
        public ?int $theme = null,
        public ?string $layout = null,
        public ?int $pad = null,
        public bool $sketch = false,
    ) {
        if (null !== $theme && $theme < 0) {
            throw new \InvalidArgumentException(\sprintf('The theme ID must be a positive integer, %d given.', $theme));
        }

        if (null !== $layout && 1 !== preg_match(self::LAYOUT_PATTERN, $layout)) {
            throw new \InvalidArgumentException(\sprintf('The layout engine "%s" is invalid: only lowercase letters, digits, "-" and "_" are allowed.', $layout));
        }

        if (null !== $pad && $pad < 0) {
            throw new \InvalidArgumentException(\sprintf('The padding must be a positive integer, %d given.', $pad));
        }
    }

    public function withTheme(?int $theme): self
    {
        return new self($theme, $this->layout, $this->pad, $this->sketch);
    }

    public function withLayout(?string $layout): self
    {
        return new self($this->theme, $layout, $this->pad, $this->sketch);
    }

    public function withPad(?int $pad): self
    {
        return new self($this->theme, $this->layout, $pad, $this->sketch);
    }

    public function withSketch(bool $sketch = true): self
    {
        return new self($this->theme, $this->layout, $this->pad, $sketch);
    }

    /**
     * Returns the d2 CLI flags matching these options, in a stable order.
     *
     * @return list<string>
     */
    public function toCliArguments(): array
    {
        $arguments = [];

        if (null !== $this->theme) {
            $arguments[] = '--theme='.$this->theme;
        }

        if (null !== $this->layout) {
            $arguments[] = '--layout='.$this->layout;
        }

        if (null !== $this->pad) {
            $arguments[] = '--pad='.$this->pad;
        }

        if ($this->sketch) {
            $arguments[] = '--sketch';
        }

        return $arguments;
    }

    /**
     * Returns a stable hash of these options, suitable as part of a cache key.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", $this->toCliArguments()));
    }
}
