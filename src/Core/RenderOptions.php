<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;

/**
 * Immutable set of options passed to the d2 renderer.
 *
 * A null value (or false for sketch) means "use the d2 default": the
 * corresponding flag is not emitted.
 */
final readonly class RenderOptions
{
    private const LAYOUT_PATTERN = '/^[a-z0-9_-]+$/';
    private const OPTION_NAMES = ['theme', 'layout', 'pad', 'sketch'];

    public function __construct(
        public ?int $theme = null,
        public ?string $layout = null,
        public ?int $pad = null,
        public bool $sketch = false,
    ) {
        if (null !== $theme && $theme < 0) {
            throw new InvalidArgumentException(\sprintf('The theme ID must be a positive integer, %d given.', $theme));
        }

        if (null !== $layout && 1 !== preg_match(self::LAYOUT_PATTERN, $layout)) {
            throw new InvalidArgumentException(\sprintf('The layout engine "%s" is invalid: only lowercase letters, digits, "-" and "_" are allowed.', $layout));
        }

        if (null !== $pad && $pad < 0) {
            throw new InvalidArgumentException(\sprintf('The padding must be a positive integer, %d given.', $pad));
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
     * Returns a copy with the given options overridden, e.g. from Twig or configuration arrays.
     *
     * Accepted keys are "theme", "layout", "pad" and "sketch"; a null value resets the
     * option to the d2 default.
     *
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidArgumentException on unknown keys, wrong types or invalid values
     */
    public function merge(array $options): self
    {
        $unknown = array_diff(array_map('strval', array_keys($options)), self::OPTION_NAMES);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown D2 option(s) "%s"; allowed options are "%s".', implode('", "', $unknown), implode('", "', self::OPTION_NAMES)));
        }

        $theme = \array_key_exists('theme', $options) ? self::nullableInt($options, 'theme') : $this->theme;
        $pad = \array_key_exists('pad', $options) ? self::nullableInt($options, 'pad') : $this->pad;
        $layout = $this->layout;
        $sketch = $this->sketch;

        if (\array_key_exists('layout', $options)) {
            $layout = $options['layout'];
            if (null !== $layout && !\is_string($layout)) {
                throw new InvalidArgumentException(\sprintf('The D2 option "layout" must be a string or null, %s given.', get_debug_type($layout)));
            }
        }

        if (\array_key_exists('sketch', $options)) {
            $sketch = $options['sketch'] ?? false;
            if (!\is_bool($sketch)) {
                throw new InvalidArgumentException(\sprintf('The D2 option "sketch" must be a boolean or null, %s given.', get_debug_type($sketch)));
            }
        }

        return new self($theme, $layout, $pad, $sketch);
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
     * Returns the options that differ from the d2 defaults, keyed by option name, in a stable order.
     *
     * Renderers that do not drive the d2 CLI (e.g. HTTP renderers) map options from this array.
     *
     * @return array<string, int|string|true>
     */
    public function toArray(): array
    {
        return array_filter([
            'theme' => $this->theme,
            'layout' => $this->layout,
            'pad' => $this->pad,
            'sketch' => $this->sketch ?: null,
        ], static fn (int|string|bool|null $value): bool => null !== $value);
    }

    /**
     * Returns a stable hash of these options, suitable as part of a cache key.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", $this->toCliArguments()));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private static function nullableInt(array $options, string $name): ?int
    {
        $value = $options[$name];
        if (null !== $value && !\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('The D2 option "%s" must be an integer or null, %s given.', $name, get_debug_type($value)));
        }

        return $value;
    }
}
