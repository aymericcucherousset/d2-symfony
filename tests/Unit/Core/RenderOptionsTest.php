<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
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
     * @return iterable<string, array{array{theme?: int, layout?: string, pad?: int}}>
     */
    public static function invalidOptionsProvider(): iterable
    {
        yield 'negative theme' => [['theme' => -1]];
        yield 'negative pad' => [['pad' => -1]];
        yield 'empty layout' => [['layout' => '']];
        yield 'uppercase layout' => [['layout' => 'ELK']];
        yield 'layout with space' => [['layout' => 'elk --foo']];
        yield 'layout with shell metacharacter' => [['layout' => 'elk;rm']];
    }

    /**
     * @param array{theme?: int, layout?: string, pad?: int} $arguments
     */
    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

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
}
