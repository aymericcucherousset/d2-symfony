<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core\Exception;

use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\D2Exception;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\NoRendererAvailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RemoteUnavailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RendererUnavailableException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\Exception\UnsupportedFormatException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Callers must be able to catch every library error with a single D2Exception,
 * while keeping the matching SPL parent.
 */
final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<\Throwable>, class-string<\Throwable>}>
     */
    public static function hierarchyProvider(): iterable
    {
        yield 'RenderException' => [RenderException::class, \RuntimeException::class];
        yield 'BinaryNotFoundException' => [BinaryNotFoundException::class, \RuntimeException::class];
        yield 'InvalidArgumentException' => [InvalidArgumentException::class, \InvalidArgumentException::class];
        yield 'RemoteUnavailableException' => [RemoteUnavailableException::class, \RuntimeException::class];
        yield 'NoRendererAvailableException' => [NoRendererAvailableException::class, \RuntimeException::class];
        yield 'UnsupportedFormatException' => [UnsupportedFormatException::class, \RuntimeException::class];
    }

    /**
     * @param class-string<\Throwable> $exception
     * @param class-string<\Throwable> $splParent
     */
    #[DataProvider('hierarchyProvider')]
    public function testExceptionIsCatchableAsD2Exception(string $exception, string $splParent): void
    {
        self::assertTrue(is_a($exception, D2Exception::class, true));
        self::assertTrue(is_a($exception, $splParent, true));
    }

    /**
     * @return iterable<string, array{class-string<\Throwable>, bool}>
     */
    public static function unavailableProvider(): iterable
    {
        yield 'BinaryNotFoundException' => [BinaryNotFoundException::class, true];
        yield 'RemoteUnavailableException' => [RemoteUnavailableException::class, true];
        yield 'NoRendererAvailableException' => [NoRendererAvailableException::class, true];
        yield 'UnsupportedFormatException' => [UnsupportedFormatException::class, true];
        yield 'RenderException' => [RenderException::class, false];
        yield 'InvalidArgumentException' => [InvalidArgumentException::class, false];
    }

    /**
     * A ChainRenderer falls back to the next renderer on unavailable errors only.
     *
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('unavailableProvider')]
    public function testOnlyUnavailableErrorsLetAChainFallBack(string $exception, bool $unavailable): void
    {
        self::assertSame($unavailable, is_a($exception, RendererUnavailableException::class, true));
    }
}
