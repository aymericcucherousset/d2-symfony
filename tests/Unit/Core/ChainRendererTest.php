<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\ChainRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\NoRendererAvailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\FailingRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\UnavailableRenderer;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\VersionedSpyRenderer;
use PHPUnit\Framework\TestCase;

final class ChainRendererTest extends TestCase
{
    public function testFirstAvailableRendererIsUsed(): void
    {
        $first = new VersionedSpyRenderer('v1');
        $second = new VersionedSpyRenderer('v2');
        $chain = new ChainRenderer([$first, $second]);

        self::assertSame('<svg data-version="v1">a -> b</svg>', $chain->render(new Diagram('a -> b')));
        self::assertSame(1, $first->renderCalls);
        self::assertSame(0, $second->renderCalls);
    }

    public function testUnavailableRendererFallsBackToTheNextOne(): void
    {
        $missing = new UnavailableRenderer();
        $spy = new SpyRenderer('<svg>fallback</svg>');
        $chain = new ChainRenderer(new \ArrayIterator([$missing, $spy]));

        self::assertSame('<svg>fallback</svg>', $chain->render(new Diagram('a -> b')));
        self::assertSame(1, $missing->renderCalls);
        self::assertCount(1, $spy->diagrams);
    }

    public function testRenderErrorsAreNotRetried(): void
    {
        $spy = new SpyRenderer();
        $chain = new ChainRenderer([new FailingRenderer('-:1:3: unexpected text'), $spy]);

        try {
            $chain->render(new Diagram('a b c'));
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException $e) {
            self::assertSame(1, $e->getD2Line());
        }

        self::assertSame([], $spy->diagrams);
    }

    public function testAllRenderersUnavailable(): void
    {
        $chain = new ChainRenderer([new UnavailableRenderer('d2-a'), new UnavailableRenderer('d2-b')]);

        try {
            $chain->render(new Diagram('a -> b'));
            self::fail('A NoRendererAvailableException should have been thrown.');
        } catch (NoRendererAvailableException $e) {
            self::assertStringStartsWith('No D2 renderer is available: 1) The d2 binary "d2-a"', $e->getMessage());
            self::assertStringContainsString(' 2) The d2 binary "d2-b"', $e->getMessage());

            $failures = $e->getFailures();
            self::assertCount(2, $failures);
            self::assertInstanceOf(BinaryNotFoundException::class, $failures[0]);
            self::assertSame($failures[1], $e->getPrevious());
        }
    }

    public function testNestedChainsFallBackToo(): void
    {
        $inner = new ChainRenderer([new UnavailableRenderer(), new UnavailableRenderer()]);
        $chain = new ChainRenderer([$inner, new SpyRenderer('<svg>outer</svg>')]);

        self::assertSame('<svg>outer</svg>', $chain->render(new Diagram('a -> b')));
    }

    public function testVersionIsTheOneOfTheFirstAvailableRenderer(): void
    {
        $missing = new UnavailableRenderer();
        $available = new VersionedSpyRenderer('v2');
        $unused = new VersionedSpyRenderer('v3');

        self::assertSame('v2', (new ChainRenderer([$missing, $available, $unused]))->getVersion());
        self::assertSame(1, $missing->versionCalls);
        self::assertSame(0, $unused->versionCalls);
    }

    public function testVersionIsEmptyWhenTheFirstAvailableRendererHasNone(): void
    {
        self::assertSame('', (new ChainRenderer([new UnavailableRenderer(), new SpyRenderer(), new VersionedSpyRenderer()]))->getVersion());
    }

    public function testVersionThrowsWhenNoRendererIsAvailable(): void
    {
        $this->expectException(NoRendererAvailableException::class);

        (new ChainRenderer([new UnavailableRenderer()]))->getVersion();
    }

    public function testEmptyChainIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The renderer chain must contain at least one renderer.');

        new ChainRenderer([]);
    }
}
