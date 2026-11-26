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
        self::assertNull($exception->getD2Line());
        self::assertNull($exception->getD2Column());
        self::assertSame([], $exception->getD2Errors());
    }

    public function testFromD2ErrorParsesTheLocationOfASyntaxError(): void
    {
        $raw = "err: failed to compile -: -:2:4: maps must be terminated with }\n";

        $exception = RenderException::fromD2Error($raw);

        self::assertSame($raw, $exception->getD2Error());
        self::assertSame('D2 syntax error at line 2, column 4: maps must be terminated with }', $exception->getMessage());
        self::assertSame(2, $exception->getD2Line());
        self::assertSame(4, $exception->getD2Column());
        self::assertSame([['line' => 2, 'column' => 4, 'message' => 'maps must be terminated with }']], $exception->getD2Errors());
    }

    public function testFromD2ErrorReportsEveryError(): void
    {
        $raw = "err: failed to compile -: -:2:15: expected \"fill\" to be a valid named color\nerr: -:3:10: unknown shape \"nope\"\nerr: -:7:1: connection missing destination\n";

        $exception = RenderException::fromD2Error($raw);

        self::assertSame('D2 syntax error at line 2, column 15: expected "fill" to be a valid named color (and 2 more errors)', $exception->getMessage());
        self::assertSame([
            ['line' => 2, 'column' => 15, 'message' => 'expected "fill" to be a valid named color'],
            ['line' => 3, 'column' => 10, 'message' => 'unknown shape "nope"'],
            ['line' => 7, 'column' => 1, 'message' => 'connection missing destination'],
        ], $exception->getD2Errors());
    }

    public function testSingleExtraErrorIsSingular(): void
    {
        $exception = RenderException::fromD2Error("err: -:1:1: a\nerr: -:2:1: b\n");

        self::assertStringEndsWith('(and 1 more error)', $exception->getMessage());
    }

    public function testFromD2ErrorWithoutLocationKeepsTheRawMessage(): void
    {
        $exception = RenderException::fromD2Error("err: layout engine crashed\n");

        self::assertSame('D2 rendering failed: err: layout engine crashed', $exception->getMessage());
        self::assertNull($exception->getD2Line());
        self::assertNull($exception->getD2Column());
    }

    public function testFromD2ErrorWithBlankErrorUsesGenericMessage(): void
    {
        $exception = RenderException::fromD2Error(" \n");

        self::assertSame('D2 rendering failed.', $exception->getMessage());
        self::assertSame(" \n", $exception->getD2Error());
    }

    public function testLocationIsParsedForDirectlyConstructedExceptions(): void
    {
        $exception = new RenderException('Custom message', 'err: -:5:2: oops');

        self::assertSame('Custom message', $exception->getMessage());
        self::assertSame(5, $exception->getD2Line());
    }

    public function testPreviousExceptionIsChained(): void
    {
        $previous = new \RuntimeException('process failed');

        self::assertSame($previous, RenderException::fromD2Error('oops', $previous)->getPrevious());
        self::assertSame($previous, (new RenderException('Boom', 'oops', $previous))->getPrevious());
    }
}
