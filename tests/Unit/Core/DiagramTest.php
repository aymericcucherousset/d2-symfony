<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiagramTest extends TestCase
{
    public function testItKeepsSourceAndDefaultsOptions(): void
    {
        $diagram = new Diagram("a -> b\n");

        self::assertSame("a -> b\n", $diagram->source);
        self::assertSame([], $diagram->options->toCliArguments());
    }

    public function testItKeepsGivenOptions(): void
    {
        $options = new RenderOptions(sketch: true);

        self::assertSame($options, (new Diagram('a -> b', $options))->options);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptySourceProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'newlines and tabs' => ["\n\t\n"];
    }

    #[DataProvider('emptySourceProvider')]
    public function testEmptySourceIsRejected(string $source): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Diagram($source);
    }

    public function testWithSourceReturnsNewInstance(): void
    {
        $original = new Diagram('a -> b');
        $changed = $original->withSource('b -> c');

        self::assertNotSame($original, $changed);
        self::assertSame('a -> b', $original->source);
        self::assertSame('b -> c', $changed->source);
        self::assertSame($original->options, $changed->options);
    }

    public function testWithSourceValidates(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Diagram('a -> b'))->withSource(' ');
    }

    public function testWithOptionsReturnsNewInstance(): void
    {
        $original = new Diagram('a -> b');
        $options = new RenderOptions(theme: 1);
        $changed = $original->withOptions($options);

        self::assertNotSame($original, $changed);
        self::assertSame([], $original->options->toCliArguments());
        self::assertSame($options, $changed->options);
        self::assertSame('a -> b', $changed->source);
    }

    public function testFingerprintIsStable(): void
    {
        $a = new Diagram('a -> b', new RenderOptions(theme: 1));
        $b = new Diagram('a -> b', new RenderOptions(theme: 1));

        self::assertSame($a->fingerprint(), $b->fingerprint());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a->fingerprint());
    }

    public function testFingerprintChangesWithSourceAndOptions(): void
    {
        $diagram = new Diagram('a -> b');

        self::assertNotSame($diagram->fingerprint(), $diagram->withSource('a -> c')->fingerprint());
        self::assertNotSame($diagram->fingerprint(), $diagram->withOptions(new RenderOptions(sketch: true))->fingerprint());
    }
}
