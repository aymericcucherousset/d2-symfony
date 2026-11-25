<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\VersionedSpyRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedRendererTest extends TestCase
{
    public function testSecondIdenticalRenderDoesNotCallTheInnerRenderer(): void
    {
        $spy = new VersionedSpyRenderer();
        $renderer = new CachedRenderer($spy, new ArrayAdapter());

        $first = $renderer->render(new Diagram('a -> b'));
        $second = $renderer->render(new Diagram('a -> b'));

        self::assertSame($first, $second);
        self::assertSame(1, $spy->renderCalls);
    }

    public function testCacheIsSharedBetweenInstancesUsingTheSamePool(): void
    {
        $pool = new ArrayAdapter();
        $spy = new VersionedSpyRenderer();

        (new CachedRenderer($spy, $pool))->render(new Diagram('a -> b'));
        (new CachedRenderer($spy, $pool))->render(new Diagram('a -> b'));

        self::assertSame(1, $spy->renderCalls);
        self::assertSame(1, $spy->versionCalls, 'The version is stored in the pool, not computed on every request.');
    }

    public function testDifferentSourceOrOptionsAreRenderedAgain(): void
    {
        $spy = new VersionedSpyRenderer();
        $renderer = new CachedRenderer($spy, new ArrayAdapter());

        $renderer->render(new Diagram('a -> b'));
        $renderer->render(new Diagram('a -> c'));
        $renderer->render(new Diagram('a -> b', new RenderOptions(sketch: true)));

        self::assertSame(3, $spy->renderCalls);
    }

    public function testEngineUpgradeInvalidatesCachedDiagrams(): void
    {
        $pool = new ArrayAdapter();
        $spy = new VersionedSpyRenderer('v1.0.0');

        (new CachedRenderer($spy, $pool))->render(new Diagram('a -> b'));

        // Simulate the expiry of the stored version after the engine was upgraded.
        $spy->version = 'v2.0.0';
        foreach (array_keys($pool->getValues()) as $key) {
            if (str_starts_with($key, 'd2.version.')) {
                $pool->deleteItem($key);
            }
        }

        $svg = (new CachedRenderer($spy, $pool))->render(new Diagram('a -> b'));

        self::assertSame(2, $spy->renderCalls);
        self::assertStringContainsString('v2.0.0', $svg);
    }

    public function testFailedRendersAreNotCached(): void
    {
        $spy = new VersionedSpyRenderer();
        $spy->fail = true;
        $renderer = new CachedRenderer($spy, new ArrayAdapter());

        try {
            $renderer->render(new Diagram('a -> {'));
            self::fail('A RenderException was expected.');
        } catch (RenderException) {
        }

        $spy->fail = false;
        $renderer->render(new Diagram('a -> {'));

        self::assertSame(2, $spy->renderCalls);
    }

    public function testRendererWithoutVersionIsCachedToo(): void
    {
        $spy = new SpyRenderer();
        $renderer = new CachedRenderer($spy, new ArrayAdapter());

        $renderer->render(new Diagram('a -> b'));
        $renderer->render(new Diagram('a -> b'));

        self::assertSame('', $renderer->getVersion());
        self::assertCount(1, $spy->diagrams);
    }

    public function testVersionIsTheInnerVersion(): void
    {
        self::assertSame('v3.1.4', (new CachedRenderer(new VersionedSpyRenderer('v3.1.4'), new ArrayAdapter()))->getVersion());
    }

    public function testVersionTtlMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CachedRenderer(new SpyRenderer(), new ArrayAdapter(), 0);
    }
}
