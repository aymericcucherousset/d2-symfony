<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Bundle;

use Aymericcucherousset\D2Symfony\D2SymfonyBundle;
use PHPUnit\Framework\TestCase;

final class D2SymfonyBundleTest extends TestCase
{
    public function testBundlePathIsPackageRoot(): void
    {
        $bundle = new D2SymfonyBundle();

        self::assertSame(\dirname(__DIR__, 3), $bundle->getPath());
    }

    public function testContainerExtensionAliasIsD2(): void
    {
        $extension = (new D2SymfonyBundle())->getContainerExtension();

        self::assertNotNull($extension);
        self::assertSame('d2', $extension->getAlias());
    }
}
