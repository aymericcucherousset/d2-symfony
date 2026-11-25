<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core\Exception;

use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use PHPUnit\Framework\TestCase;

final class BinaryNotFoundExceptionTest extends TestCase
{
    public function testItDescribesTheMissingBinary(): void
    {
        $exception = new BinaryNotFoundException('/usr/local/bin/d2');

        self::assertSame('/usr/local/bin/d2', $exception->getBinary());
        self::assertStringContainsString('"/usr/local/bin/d2"', $exception->getMessage());
        self::assertStringContainsString('https://d2lang.com/tour/install', $exception->getMessage());
    }

    public function testPreviousExceptionIsChained(): void
    {
        $previous = new \RuntimeException('exec failed');

        self::assertSame($previous, (new BinaryNotFoundException('d2', $previous))->getPrevious());
    }
}
