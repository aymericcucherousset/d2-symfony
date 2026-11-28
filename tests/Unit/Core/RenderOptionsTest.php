<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Format;
use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Core\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RenderOptionsTest extends TestCase
{
    public function testDefaultsProduceNoArguments(): void
    {
        $options = new RenderOptions();

        self::assertNull($options->theme);
        self::assertNull($options->layout);
        self::assertNull($options->pad);
        self::assertFalse($options->sketch);
        self::assertSame([], $options->toCliArguments());
    }

    public function testAllOptionsProduceArgumentsInStableOrder(): void
    {
        $options = new RenderOptions(theme: 200, layout: 'elk', pad: 20, sketch: true);

        self::assertSame(['--theme=200', '--layout=elk', '--pad=20', '--sketch'], $options->toCliArguments());
    }

    public function testToArrayOnlyContainsNonDefaultOptions(): void
    {
        self::assertSame([], (new RenderOptions())->toArray());
        self::assertSame(['theme' => 0, 'pad' => 0], (new RenderOptions(theme: 0, pad: 0))->toArray());
        self::assertSame(
            ['theme' => 200, 'layout' => 'elk', 'pad' => 20, 'sketch' => true],
            (new RenderOptions(theme: 200, layout: 'elk', pad: 20, sketch: true))->toArray(),
        );
    }

    public function testZeroValuesAreEmitted(): void
    {
        $options = new RenderOptions(theme: 0, pad: 0);

        self::assertSame(['--theme=0', '--pad=0'], $options->toCliArguments());
    }

    public function testWithersReturnNewInstancesAndKeepOriginalUnchanged(): void
    {
        $original = new RenderOptions();

        $withTheme = $original->withTheme(1);
        $withLayout = $original->withLayout('elk');
        $withPad = $original->withPad(10);
        $withSketch = $original->withSketch();

        self::assertNotSame($original, $withTheme);
        self::assertNotSame($original, $withLayout);
        self::assertNotSame($original, $withPad);
        self::assertNotSame($original, $withSketch);

        self::assertSame(['--theme=1'], $withTheme->toCliArguments());
        self::assertSame(['--layout=elk'], $withLayout->toCliArguments());
        self::assertSame(['--pad=10'], $withPad->toCliArguments());
        self::assertSame(['--sketch'], $withSketch->toCliArguments());

        self::assertSame([], $original->toCliArguments());
    }

    public function testWithersPreserveOtherOptions(): void
    {
        $options = (new RenderOptions())
            ->withTheme(3)
            ->withLayout('dagre')
            ->withPad(50)
            ->withSketch();

        self::assertSame(['--theme=3', '--layout=dagre', '--pad=50', '--sketch'], $options->toCliArguments());
    }

    public function testWithersCanResetToDefaults(): void
    {
        $options = (new RenderOptions(theme: 1, layout: 'elk', pad: 5, sketch: true))
            ->withTheme(null)
            ->withLayout(null)
            ->withPad(null)
            ->withSketch(false);

        self::assertSame([], $options->toCliArguments());
    }

    /**
     * @return iterable<string, array{array<string, int|float|string>}>
     */
    public static function invalidOptionsProvider(): iterable
    {
        yield 'negative theme' => [['theme' => -1]];
        yield 'negative pad' => [['pad' => -1]];
        yield 'empty layout' => [['layout' => '']];
        yield 'uppercase layout' => [['layout' => 'ELK']];
        yield 'layout with space' => [['layout' => 'elk --foo']];
        yield 'layout with shell metacharacter' => [['layout' => 'elk;rm']];
        yield 'unknown theme' => [['theme' => 2]];
        yield 'unknown dark theme' => [['darkTheme' => 999]];
        yield 'unknown layout' => [['layout' => 'grid']];
        yield 'zero scale' => [['scale' => 0.0]];
        yield 'negative scale' => [['scale' => -1.0]];
        yield 'infinite scale' => [['scale' => \INF]];
        yield 'empty font' => [['fontRegular' => '']];
        yield 'blank font' => [['fontBold' => '  ']];
        yield 'font with NUL byte' => [['fontItalic' => "a\0.ttf"]];
    }

    /**
     * @param array<string, int|float|string> $arguments
     */
    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type */
        new RenderOptions(...$arguments);
    }

    public function testWithersValidate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RenderOptions())->withPad(-5);
    }

    public function testFingerprintIsStableForEqualOptions(): void
    {
        $a = new RenderOptions(theme: 1, layout: 'elk', pad: 10, sketch: true);
        $b = (new RenderOptions())->withSketch()->withPad(10)->withLayout('elk')->withTheme(1);

        self::assertSame($a->fingerprint(), $b->fingerprint());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a->fingerprint());
    }

    public function testFingerprintChangesWithEachOption(): void
    {
        $base = new RenderOptions();

        $fingerprints = [
            $base->fingerprint(),
            $base->withTheme(1)->fingerprint(),
            $base->withLayout('elk')->fingerprint(),
            $base->withPad(10)->fingerprint(),
            $base->withSketch()->fingerprint(),
        ];

        self::assertSame($fingerprints, array_unique($fingerprints));
    }

    public function testMergeOverridesOnlyGivenOptions(): void
    {
        $options = new RenderOptions(theme: 1, layout: 'dagre', pad: 5);

        self::assertEquals(new RenderOptions(theme: 1, layout: 'elk', pad: 5, sketch: true), $options->merge(['layout' => 'elk', 'sketch' => true]));
        self::assertEquals($options, $options->merge([]));
        self::assertEquals(new RenderOptions(theme: 1, layout: 'dagre', pad: 5), $options, 'The original is unchanged.');
    }

    public function testMergeWithNullResetsToD2Defaults(): void
    {
        $options = new RenderOptions(theme: 1, layout: 'elk', pad: 5, sketch: true);

        self::assertEquals(new RenderOptions(), $options->merge(['theme' => null, 'layout' => null, 'pad' => null, 'sketch' => null]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidMergeProvider(): iterable
    {
        yield 'unknown key' => [['them' => 1], 'Unknown D2 option(s) "them"'];
        yield 'numeric key' => [[0 => 1], 'Unknown D2 option(s) "0"'];
        yield 'string theme' => [['theme' => '200'], '"theme" must be a theme ID or null, string given'];
        yield 'float pad' => [['pad' => 1.5], '"pad" must be an integer or null, float given'];
        yield 'int layout' => [['layout' => 1], '"layout" must be a layout name or null, int given'];
        yield 'int sketch' => [['sketch' => 1], '"sketch" must be a boolean or null, int given'];
        yield 'invalid layout value' => [['layout' => 'ELK'], 'Unknown layout engine "ELK"; available layout engines are "dagre", "elk", "tala"'];
        yield 'negative theme value' => [['theme' => -1], 'Unknown d2 theme ID -1 for "theme"'];
        yield 'unknown dark theme value' => [['dark_theme' => 42], 'Unknown d2 theme ID 42 for "dark_theme"; available themes are 0 (NeutralDefault), 1 (NeutralGrey)'];
        yield 'string dark theme' => [['dark_theme' => '200'], '"dark_theme" must be a theme ID or null, string given'];
        yield 'string center' => [['center' => 'yes'], '"center" must be a boolean or null, string given'];
        yield 'string scale' => [['scale' => '0.5'], '"scale" must be a number or null, string given'];
        yield 'zero scale' => [['scale' => 0], 'The scale must be a number greater than 0, 0 given.'];
        yield 'int font' => [['font_bold' => 1], '"font_bold" must be a file path or null, int given'];
        yield 'empty font' => [['font_semibold' => ''], 'The D2 option "font_semibold" must be the path to a .ttf file.'];
        yield 'camelCase key' => [['darkTheme' => 200], 'Unknown D2 option(s) "darkTheme"'];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[DataProvider('invalidMergeProvider')]
    public function testMergeRejectsInvalidOptions(array $options, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new RenderOptions())->merge($options);
    }

    public function testThemesAndLayoutsAreTyped(): void
    {
        $options = new RenderOptions(theme: 200, layout: 'elk', darkTheme: 201);

        self::assertSame(Theme::DarkMauve, $options->theme);
        self::assertSame(Theme::DarkFlagshipTerrastruct, $options->darkTheme);
        self::assertSame(Layout::Elk, $options->layout);
        self::assertEquals($options, new RenderOptions(theme: Theme::DarkMauve, layout: Layout::Elk, darkTheme: Theme::DarkFlagshipTerrastruct));
    }

    public function testNewOptionsProduceArgumentsInStableOrder(): void
    {
        $options = new RenderOptions(
            theme: Theme::NeutralDefault,
            layout: Layout::Elk,
            pad: 20,
            sketch: true,
            darkTheme: Theme::DarkMauve,
            center: true,
            scale: 0.5,
            fontRegular: '/fonts/r.ttf',
            fontItalic: '/fonts/i.ttf',
            fontBold: '/fonts/b.ttf',
            fontSemibold: '/fonts/s.ttf',
        );

        self::assertSame([
            '--theme=0',
            '--dark-theme=200',
            '--layout=elk',
            '--pad=20',
            '--scale=0.5',
            '--center',
            '--sketch',
            '--font-regular=/fonts/r.ttf',
            '--font-italic=/fonts/i.ttf',
            '--font-bold=/fonts/b.ttf',
            '--font-semibold=/fonts/s.ttf',
        ], $options->toCliArguments());
        self::assertSame([
            'theme' => 0,
            'dark_theme' => 200,
            'layout' => 'elk',
            'pad' => 20,
            'scale' => 0.5,
            'center' => true,
            'sketch' => true,
            'font_regular' => '/fonts/r.ttf',
            'font_italic' => '/fonts/i.ttf',
            'font_bold' => '/fonts/b.ttf',
            'font_semibold' => '/fonts/s.ttf',
        ], $options->toArray());
    }

    public function testScaleIsFormattedWithoutTrailingZeros(): void
    {
        self::assertSame(['--scale=1'], (new RenderOptions(scale: 1.0))->toCliArguments());
        self::assertSame(['--scale=2.25'], (new RenderOptions(scale: 2.25))->toCliArguments());
    }

    public function testFingerprintOfTheOriginalOptionsIsUnchanged(): void
    {
        // Cache keys of diagrams rendered before dark_theme, center, scale and fonts existed must not change.
        self::assertSame(
            hash('sha256', "--theme=200\0--layout=elk\0--pad=20\0--sketch"),
            (new RenderOptions(theme: 200, layout: 'elk', pad: 20, sketch: true))->fingerprint(),
        );
    }

    public function testNewWithers(): void
    {
        $options = (new RenderOptions())
            ->withDarkTheme(Theme::DarkMauve)
            ->withCenter()
            ->withScale(2.0)
            ->withFonts(regular: '/fonts/r.ttf', bold: '/fonts/b.ttf');

        self::assertSame(['--dark-theme=200', '--scale=2', '--center', '--font-regular=/fonts/r.ttf', '--font-bold=/fonts/b.ttf'], $options->toCliArguments());
        self::assertSame([], $options->withDarkTheme(null)->withCenter(false)->withScale(null)->withFonts()->toCliArguments());
    }

    public function testMergeAcceptsNewOptionsAndEnums(): void
    {
        $options = (new RenderOptions())->merge([
            'theme' => Theme::Terminal,
            'dark_theme' => 201,
            'layout' => Layout::Dagre,
            'center' => true,
            'scale' => 2,
            'font_italic' => '/fonts/i.ttf',
        ]);

        self::assertEquals(new RenderOptions(theme: 300, layout: 'dagre', darkTheme: 201, center: true, scale: 2.0, fontItalic: '/fonts/i.ttf'), $options);
        self::assertEquals(new RenderOptions(theme: 300, layout: 'dagre'), $options->merge(['dark_theme' => null, 'center' => null, 'scale' => null, 'font_italic' => null]));
    }

    public function testFormatDefaultsToSvgWithoutArguments(): void
    {
        $options = new RenderOptions();

        self::assertSame(Format::Svg, $options->format);
        self::assertSame([], $options->toArray());
        self::assertSame([], $options->withFormat(Format::Svg)->toCliArguments());
        self::assertSame($options->fingerprint(), $options->withFormat('svg')->fingerprint());
    }

    public function testFormatIsPassedAsStdoutFormat(): void
    {
        $options = new RenderOptions(theme: 200, sketch: true, format: Format::Png);

        self::assertSame(['--theme=200', '--sketch', '--stdout-format=png'], $options->toCliArguments());
        self::assertSame(['theme' => 200, 'sketch' => true, 'format' => 'png'], $options->toArray());
        self::assertSame(['--stdout-format=pdf'], (new RenderOptions(format: 'pdf'))->toCliArguments());
    }

    public function testFormatChangesTheFingerprint(): void
    {
        $fingerprints = array_map(static fn (Format $format): string => (new RenderOptions(format: $format))->fingerprint(), Format::cases());

        self::assertSame($fingerprints, array_unique($fingerprints));
    }

    public function testFormatCanBeMergedAndReset(): void
    {
        $options = (new RenderOptions(theme: 1))->merge(['format' => 'png']);

        self::assertEquals(new RenderOptions(theme: 1, format: Format::Png), $options);
        self::assertSame(Format::Pdf, $options->merge(['format' => Format::Pdf])->format);
        self::assertSame(Format::Svg, $options->merge(['format' => null])->format);
        self::assertSame(Format::Png, $options->withTheme(3)->format, 'Withers keep the format.');
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown output format "jpg"; available formats are "svg", "png", "pdf".');

        new RenderOptions(format: 'jpg');
    }

    public function testMergeRejectsANonStringFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The D2 option "format" must be a format name or null, int given.');

        (new RenderOptions())->merge(['format' => 1]);
    }

    public function testFormatMetadata(): void
    {
        self::assertSame(['image/svg+xml', 'image/png', 'application/pdf'], array_map(static fn (Format $format): string => $format->mimeType(), Format::cases()));
        self::assertSame(Format::Png, Format::fromExtension('.PNG'));
        self::assertSame(Format::Pdf, Format::fromExtension('pdf'));
        self::assertNull(Format::fromExtension('d2'));
    }

    public function testThemeEnumMatchesTheOfficialThemes(): void
    {
        // IDs listed by "d2 themes" (d2 v0.9.0).
        $ids = array_map(static fn (Theme $theme): int => $theme->value, Theme::cases());
        sort($ids);
        self::assertSame([0, 1, 3, 4, 5, 6, 7, 8, 100, 101, 102, 103, 104, 105, 200, 201, 300, 301, 302, 303], $ids);
        self::assertSame([Theme::DarkMauve, Theme::DarkFlagshipTerrastruct], array_values(array_filter(Theme::cases(), static fn (Theme $theme): bool => $theme->isDark())));
        self::assertSame(['dagre', 'elk', 'tala'], Layout::values());
    }
}
