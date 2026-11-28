<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;

/**
 * Immutable set of options passed to the d2 renderer.
 *
 * A null value (or false for flags) means "use the d2 default": the
 * corresponding flag is not emitted.
 *
 * Option names, as used by {@see merge()}, {@see toArray()}, Twig and the bundle
 * configuration: theme, dark_theme, layout, pad, sketch, center, scale,
 * font_regular, font_italic, font_bold and font_semibold.
 */
final readonly class RenderOptions
{
    /** Option name => constructor parameter. */
    private const OPTIONS = [
        'theme' => 'theme',
        'dark_theme' => 'darkTheme',
        'layout' => 'layout',
        'pad' => 'pad',
        'sketch' => 'sketch',
        'center' => 'center',
        'scale' => 'scale',
        'font_regular' => 'fontRegular',
        'font_italic' => 'fontItalic',
        'font_bold' => 'fontBold',
        'font_semibold' => 'fontSemibold',
    ];

    public ?Theme $theme;
    public ?Theme $darkTheme;
    public ?Layout $layout;

    /**
     * @param Theme|int|null     $theme        theme of the diagram, or of its light mode when $darkTheme is set
     * @param Layout|string|null $layout       layout engine
     * @param int|null           $pad          padding around the diagram, in pixels (d2 default: 100)
     * @param bool               $sketch       hand-drawn look
     * @param Theme|int|null     $darkTheme    theme used when the viewer's browser is in dark mode
     * @param bool               $center       center the SVG in its viewbox
     * @param float|null         $scale        scale factor (e.g. 0.5 halves the size); null fits SVG to the screen
     * @param string|null        $fontRegular  path to the .ttf file used for regular text
     * @param string|null        $fontItalic   path to the .ttf file used for italic text
     * @param string|null        $fontBold     path to the .ttf file used for bold text
     * @param string|null        $fontSemibold path to the .ttf file used for semibold text
     *
     * @throws InvalidArgumentException on unknown themes or layouts, or out of range values
     */
    public function __construct(
        Theme|int|null $theme = null,
        Layout|string|null $layout = null,
        public ?int $pad = null,
        public bool $sketch = false,
        Theme|int|null $darkTheme = null,
        public bool $center = false,
        public ?float $scale = null,
        public ?string $fontRegular = null,
        public ?string $fontItalic = null,
        public ?string $fontBold = null,
        public ?string $fontSemibold = null,
    ) {
        $this->theme = self::theme($theme, 'theme');
        $this->darkTheme = self::theme($darkTheme, 'dark_theme');
        $this->layout = self::layout($layout);

        if (null !== $pad && $pad < 0) {
            throw new InvalidArgumentException(\sprintf('The padding must be a positive integer, %d given.', $pad));
        }

        if (null !== $scale && (!is_finite($scale) || $scale <= 0)) {
            throw new InvalidArgumentException(\sprintf('The scale must be a number greater than 0, %s given.', $scale));
        }

        foreach (['font_regular' => $fontRegular, 'font_italic' => $fontItalic, 'font_bold' => $fontBold, 'font_semibold' => $fontSemibold] as $name => $font) {
            if (null !== $font && ('' === trim($font) || str_contains($font, "\0"))) {
                throw new InvalidArgumentException(\sprintf('The D2 option "%s" must be the path to a .ttf file.', $name));
            }
        }
    }

    public function withTheme(Theme|int|null $theme): self
    {
        return $this->with(['theme' => $theme]);
    }

    public function withDarkTheme(Theme|int|null $darkTheme): self
    {
        return $this->with(['darkTheme' => $darkTheme]);
    }

    public function withLayout(Layout|string|null $layout): self
    {
        return $this->with(['layout' => $layout]);
    }

    public function withPad(?int $pad): self
    {
        return $this->with(['pad' => $pad]);
    }

    public function withSketch(bool $sketch = true): self
    {
        return $this->with(['sketch' => $sketch]);
    }

    public function withCenter(bool $center = true): self
    {
        return $this->with(['center' => $center]);
    }

    public function withScale(?float $scale): self
    {
        return $this->with(['scale' => $scale]);
    }

    /**
     * Returns a copy using the given .ttf files; a null font uses the d2 default font.
     */
    public function withFonts(?string $regular = null, ?string $italic = null, ?string $bold = null, ?string $semibold = null): self
    {
        return $this->with(['fontRegular' => $regular, 'fontItalic' => $italic, 'fontBold' => $bold, 'fontSemibold' => $semibold]);
    }

    /**
     * Returns a copy with the given options overridden, e.g. from Twig or configuration arrays.
     *
     * Keys are option names (see the class description); a null value resets the option to
     * the d2 default. Themes accept a {@see Theme} or its ID, layouts a {@see Layout} or its name.
     *
     * @param array<array-key, mixed> $options
     *
     * @throws InvalidArgumentException on unknown keys, wrong types or invalid values
     */
    public function merge(array $options): self
    {
        $unknown = array_diff(array_map('strval', array_keys($options)), array_keys(self::OPTIONS));
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('Unknown D2 option(s) "%s"; allowed options are "%s".', implode('", "', $unknown), implode('", "', array_keys(self::OPTIONS))));
        }

        $changes = [];
        foreach ($options as $name => $value) {
            $name = (string) $name;
            $changes[self::OPTIONS[$name]] = match ($name) {
                'theme', 'dark_theme' => self::typed($name, $value, Theme::class, 'int', 'a theme ID'),
                'layout' => self::typed($name, $value, Layout::class, 'string', 'a layout name'),
                'pad' => self::typed($name, $value, null, 'int', 'an integer'),
                'sketch', 'center' => self::typed($name, $value, null, 'bool', 'a boolean') ?? false,
                'scale' => \is_int($value) ? (float) $value : self::typed($name, $value, null, 'float', 'a number'),
                default => self::typed($name, $value, null, 'string', 'a file path'),
            };
        }

        return $this->with($changes);
    }

    /**
     * Returns the d2 CLI flags matching these options, in a stable order.
     *
     * @return list<string>
     */
    public function toCliArguments(): array
    {
        $arguments = [];

        foreach ($this->toArray() as $name => $value) {
            $flag = '--'.str_replace('_', '-', $name);
            $arguments[] = true === $value ? $flag : $flag.'='.$value;
        }

        return $arguments;
    }

    /**
     * Returns the options that differ from the d2 defaults, keyed by option name, in a stable order.
     *
     * Renderers that do not drive the d2 CLI (e.g. HTTP renderers) map options from this array.
     *
     * @return array<string, int|float|string|true>
     */
    public function toArray(): array
    {
        // The order of the original options (theme, layout, pad, sketch) is kept, so cache keys do not change.
        return array_filter([
            'theme' => $this->theme?->value,
            'dark_theme' => $this->darkTheme?->value,
            'layout' => $this->layout?->value,
            'pad' => $this->pad,
            'scale' => $this->scale,
            'center' => $this->center ?: null,
            'sketch' => $this->sketch ?: null,
            'font_regular' => $this->fontRegular,
            'font_italic' => $this->fontItalic,
            'font_bold' => $this->fontBold,
            'font_semibold' => $this->fontSemibold,
        ], static fn (int|float|string|bool|null $value): bool => null !== $value);
    }

    /**
     * Returns a stable hash of these options, suitable as part of a cache key.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", $this->toCliArguments()));
    }

    /**
     * @param array<string, mixed> $changes constructor arguments to override
     */
    private function with(array $changes): self
    {
        /* @phpstan-ignore argument.type (values are type-checked by the constructor) */
        return new self(...[
            'theme' => $this->theme,
            'layout' => $this->layout,
            'pad' => $this->pad,
            'sketch' => $this->sketch,
            'darkTheme' => $this->darkTheme,
            'center' => $this->center,
            'scale' => $this->scale,
            'fontRegular' => $this->fontRegular,
            'fontItalic' => $this->fontItalic,
            'fontBold' => $this->fontBold,
            'fontSemibold' => $this->fontSemibold,
            ...$changes,
        ]);
    }

    /**
     * @param class-string<\BackedEnum>|null $enum   an enum also accepted for the option
     * @param 'int'|'string'|'bool'|'float'  $scalar the scalar type accepted for the option
     */
    private static function typed(string $name, mixed $value, ?string $enum, string $scalar, string $expected): mixed
    {
        if (null === $value || (null !== $enum && $value instanceof $enum) || get_debug_type($value) === $scalar) {
            return $value;
        }

        throw new InvalidArgumentException(\sprintf('The D2 option "%s" must be %s or null, %s given.', $name, $expected, get_debug_type($value)));
    }

    private static function theme(Theme|int|null $theme, string $name): ?Theme
    {
        if (!\is_int($theme)) {
            return $theme;
        }

        return Theme::tryFrom($theme) ?? throw new InvalidArgumentException(\sprintf('Unknown d2 theme ID %d for "%s"; available themes are %s.', $theme, $name, implode(', ', array_map(static fn (Theme $theme): string => $theme->value.' ('.$theme->name.')', Theme::cases()))));
    }

    private static function layout(Layout|string|null $layout): ?Layout
    {
        if (!\is_string($layout)) {
            return $layout;
        }

        return Layout::tryFrom($layout) ?? throw new InvalidArgumentException(\sprintf('Unknown layout engine "%s"; available layout engines are "%s".', $layout, implode('", "', Layout::values())));
    }
}
