<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Bundle;

use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\Theme;
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
            'renderer' => 'binary',
            'chain' => ['binary', 'kroki'],
            'kroki' => ['base_url' => 'https://kroki.io', 'http_client' => 'http_client'],
            'binary_path' => null,
            'binary_version' => 'v0.9.0',
            'install_dir' => '%kernel.project_dir%/var/d2',
            'timeout' => 60.0,
            'defaults' => ['theme' => null, 'dark_theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false, 'center' => false, 'scale' => null, 'font_regular' => null, 'font_italic' => null, 'font_bold' => null, 'font_semibold' => null],
            'cache' => ['enabled' => true, 'pool' => 'cache.d2'],
            'twig' => ['prerender_static' => null, 'root_dir' => '%kernel.project_dir%'],
        ], self::process([]));
    }

    public function testFullConfiguration(): void
    {
        self::assertSame([
            'renderer' => 'chain',
            'chain' => ['kroki', 'binary'],
            'kroki' => ['base_url' => 'https://kroki.example.com/kroki', 'http_client' => 'kroki.client'],
            'binary_path' => '/usr/local/bin/d2',
            'binary_version' => '0.8.1',
            'install_dir' => '/opt/d2',
            'timeout' => 12.5,
            'defaults' => [
                'theme' => 200,
                'dark_theme' => 201,
                'layout' => 'elk',
                'pad' => 0,
                'sketch' => true,
                'center' => true,
                'scale' => 0.5,
                'font_regular' => '/fonts/regular.ttf',
                'font_italic' => '/fonts/italic.ttf',
                'font_bold' => '/fonts/bold.ttf',
                'font_semibold' => '/fonts/semibold.ttf',
            ],
            'cache' => ['pool' => 'cache.redis', 'enabled' => true],
            'twig' => ['prerender_static' => false, 'root_dir' => '/srv/diagrams'],
        ], self::process([
            'renderer' => 'chain',
            'chain' => ['kroki', 'binary'],
            'kroki' => ['base_url' => 'https://kroki.example.com/kroki', 'http_client' => 'kroki.client'],
            'binary_path' => '/usr/local/bin/d2',
            'binary_version' => '0.8.1',
            'install_dir' => '/opt/d2',
            'timeout' => 12.5,
            'defaults' => [
                'theme' => 200,
                'dark_theme' => 201,
                'layout' => 'elk',
                'pad' => 0,
                'sketch' => true,
                'center' => true,
                'scale' => 0.5,
                'font_regular' => '/fonts/regular.ttf',
                'font_italic' => '/fonts/italic.ttf',
                'font_bold' => '/fonts/bold.ttf',
                'font_semibold' => '/fonts/semibold.ttf',
            ],
            'cache' => ['pool' => 'cache.redis'],
            'twig' => ['prerender_static' => false, 'root_dir' => '/srv/diagrams'],
        ]));
    }

    public function testPrerenderStaticAcceptsBooleansAndNull(): void
    {
        self::assertSame(['prerender_static' => true, 'root_dir' => '%kernel.project_dir%'], self::process(['twig' => ['prerender_static' => true]])['twig']);
        self::assertSame(['prerender_static' => null, 'root_dir' => '%kernel.project_dir%'], self::process(['twig' => ['prerender_static' => null]])['twig']);
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

    public function testEnumsAndIntegerScaleAreAcceptedInDefaults(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = self::process(['defaults' => ['theme' => Theme::DarkMauve, 'dark_theme' => Theme::DarkFlagshipTerrastruct, 'layout' => Layout::Elk, 'scale' => 2]])['defaults'];

        self::assertSame(200, $defaults['theme']);
        self::assertSame(201, $defaults['dark_theme']);
        self::assertSame('elk', $defaults['layout']);
        self::assertEquals(2.0, $defaults['scale']);
    }

    public function testNullDefaultsMeanD2Defaults(): void
    {
        self::assertSame(
            ['theme' => null, 'dark_theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false, 'center' => false, 'scale' => null, 'font_regular' => null, 'font_italic' => null, 'font_bold' => null, 'font_semibold' => null],
            self::process(['defaults' => ['theme' => null, 'layout' => null, 'pad' => null]])['defaults'],
        );
        self::assertSame(
            ['theme' => null, 'dark_theme' => null, 'layout' => null, 'pad' => null, 'sketch' => false, 'center' => false, 'scale' => null, 'font_regular' => null, 'font_italic' => null, 'font_bold' => null, 'font_semibold' => null],
            self::process(['defaults' => null])['defaults'],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'empty binary path' => [['binary_path' => '']];
        yield 'invalid binary version' => [['binary_version' => 'latest']];
        yield 'partial binary version' => [['binary_version' => 'v0.9']];
        yield 'empty install dir' => [['install_dir' => '']];
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
        yield 'string prerender_static' => [['twig' => ['prerender_static' => 'yes']]];
        yield 'empty Twig root_dir' => [['twig' => ['root_dir' => '']]];
        yield 'non-string Twig root_dir' => [['twig' => ['root_dir' => ['diagrams']]]];
        yield 'unknown option' => [['engine' => 'kroki']];
        yield 'unknown renderer' => [['renderer' => 'wasm']];
        yield 'empty chain' => [['chain' => []]];
        yield 'unknown chained renderer' => [['chain' => ['binary', 'wasm']]];
        yield 'duplicate chained renderer' => [['chain' => ['binary', 'binary']]];
        yield 'nested chain' => [['chain' => ['chain']]];
        yield 'relative Kroki URL' => [['kroki' => ['base_url' => 'kroki.io']]];
        yield 'non-http Kroki URL' => [['kroki' => ['base_url' => 'ftp://kroki.io']]];
        yield 'non-string Kroki URL' => [['kroki' => ['base_url' => 42]]];
        yield 'empty Kroki HTTP client' => [['kroki' => ['http_client' => '']]];
        yield 'unknown default' => [['defaults' => ['font_mono' => '/fonts/mono.ttf']]];
        yield 'unknown theme' => [['defaults' => ['theme' => 2]]];
        yield 'unknown dark theme' => [['defaults' => ['dark_theme' => 42]]];
        yield 'negative dark theme' => [['defaults' => ['dark_theme' => -1]]];
        yield 'unknown layout' => [['defaults' => ['layout' => 'grid']]];
        yield 'zero scale' => [['defaults' => ['scale' => 0]]];
        yield 'negative scale' => [['defaults' => ['scale' => -0.5]]];
        yield 'string scale' => [['defaults' => ['scale' => 'big']]];
        yield 'string center' => [['defaults' => ['center' => 'yes']]];
        yield 'empty font' => [['defaults' => ['font_bold' => '']]];
        yield 'non-string font' => [['defaults' => ['font_regular' => 12]]];
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
