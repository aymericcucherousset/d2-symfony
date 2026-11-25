<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core\Exception;

use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use PHPUnit\Framework\TestCase;

final class RenderExceptionTest extends TestCase
{
    public function testItKeepsTheGivenMessage(): void
    {
        $exception = new RenderException('Boom');

        self::assertSame('Boom', $exception->getMessage());
        self::assertSame('', $exception->getD2Error());
    }

    public function testFromD2ErrorKeepsRawErrorAndBuildsTrimmedMessage(): void
    {
        $raw = "err: failed to compile -: 1:4: unexpected text after map key\n";

        $exception = RenderException::fromD2Error($raw);

        self::assertSame($raw, $exception->getD2Error());
        self::assertSame('D2 rendering failed: err: failed to compile -: 1:4: unexpected text after map key', $exception->getMessage());
    }

    public function testFromD2ErrorWithBlankErrorUsesGenericMessage(): void
    {
        $exception = RenderException::fromD2Error(" \n");

        self::assertSame('D2 rendering failed.', $exception->getMessage());
        self::assertSame(" \n", $exception->getD2Error());
    }

    public function testPreviousExceptionIsChained(): void
    {
        $previous = new \RuntimeException('process failed');

        self::assertSame($previous, RenderException::fromD2Error('oops', $previous)->getPrevious());
        self::assertSame($previous, (new RenderException('Boom', 'oops', $previous))->getPrevious());
    }
}
