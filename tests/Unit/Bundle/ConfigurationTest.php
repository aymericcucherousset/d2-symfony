<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Bundle;

use Aymericcucherousset\D2Symfony\D2SymfonyBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

final class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        self::assertSame([
            'binary_path' => 'd2',
            'timeout' => 60.0,
            'defaults' => ['theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false],
            'cache' => ['enabled' => true, 'pool' => 'cache.d2'],
        ], self::process([]));
    }

    public function testFullConfiguration(): void
    {
        self::assertSame([
            'binary_path' => '/usr/local/bin/d2',
            'timeout' => 12.5,
            'defaults' => ['theme' => 200, 'layout' => 'elk', 'pad' => 0, 'sketch' => true],
            'cache' => ['pool' => 'cache.redis', 'enabled' => true],
        ], self::process([
            'binary_path' => '/usr/local/bin/d2',
            'timeout' => 12.5,
            'defaults' => ['theme' => 200, 'layout' => 'elk', 'pad' => 0, 'sketch' => true],
            'cache' => ['pool' => 'cache.redis'],
        ]));
    }

    public function testCacheCanBeDisabled(): void
    {
        self::assertSame(['enabled' => false, 'pool' => 'cache.d2'], self::process(['cache' => false])['cache']);
        self::assertSame(['enabled' => false, 'pool' => 'cache.d2'], self::process(['cache' => ['enabled' => false]])['cache']);
    }

    public function testTimeoutAcceptsIntegersAndNull(): void
    {
        self::assertSame(30, self::process(['timeout' => 30])['timeout']);
        self::assertNull(self::process(['timeout' => null])['timeout']);
    }

    public function testNullDefaultsMeanD2Defaults(): void
    {
        self::assertSame(
            ['theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false],
            self::process(['defaults' => ['theme' => null, 'layout' => null, 'pad' => null]])['defaults'],
        );
        self::assertSame(
            ['theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false],
            self::process(['defaults' => null])['defaults'],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty binary path' => [['binary_path' => '']];
        yield 'null binary path' => [['binary_path' => null]];
        yield 'non-string binary path' => [['binary_path' => 42]];
        yield 'zero timeout' => [['timeout' => 0]];
        yield 'negative timeout' => [['timeout' => -1.5]];
        yield 'string timeout' => [['timeout' => 'abc']];
        yield 'negative theme' => [['defaults' => ['theme' => -1]]];
        yield 'string theme' => [['defaults' => ['theme' => 'dark']]];
        yield 'negative pad' => [['defaults' => ['pad' => -1]]];
        yield 'uppercase layout' => [['defaults' => ['layout' => 'ELK']]];
        yield 'layout with flag' => [['defaults' => ['layout' => 'elk --foo']]];
        yield 'non-string layout' => [['defaults' => ['layout' => ['elk']]]];
        yield 'string sketch' => [['defaults' => ['sketch' => 'yes']]];
        yield 'empty cache pool' => [['cache' => ['pool' => '']]];
        yield 'non-string cache pool' => [['cache' => ['pool' => 42]]];
        yield 'unknown option' => [['renderer' => 'kroki']];
        yield 'unknown default' => [['defaults' => ['scale' => 2]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function testInvalidConfigurationIsRejected(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::process($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private static function process(array $config): array
    {
        $extension = (new D2SymfonyBundle())->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);

        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        return (new Processor())->processConfiguration($configuration, [$config]);
    }
}
