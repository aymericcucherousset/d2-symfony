<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\Bundle;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\D2SymfonyBundle;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\Bundle\RendererConsumer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Nyholm\BundleTest\TestKernel;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

final class D2SymfonyBundleKernelTest extends KernelTestCase
{
    private const FAKE_D2 = __DIR__.'/../../Fixtures/bin/fake-d2-echo';
    private const FAKE_D2_VERSION = __DIR__.'/../../Fixtures/bin/fake-d2-version';
    private const TEMPLATES = __DIR__.'/../../Fixtures/templates';

    /** @var callable|null */
    private $initialExceptionHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initialExceptionHandler = self::currentExceptionHandler();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // FrameworkBundle::boot() registers Symfony's ErrorHandler as exception handler and older
        // versions (< 7) never remove it, which PHPUnit 11 reports as a risky test.
        for ($i = 0; $i < 10 && self::currentExceptionHandler() !== $this->initialExceptionHandler; ++$i) {
            restore_exception_handler();
        }
    }

    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        $kernel = parent::createKernel($options);
        self::assertInstanceOf(TestKernel::class, $kernel);

        $kernel->addTestBundle(TwigBundle::class);
        $kernel->addTestBundle(D2SymfonyBundle::class);
        $kernel->addTestConfig(static function (ContainerBuilder $container): void {
            $container->register(RendererConsumer::class)->setAutowired(true)->setPublic(true);
        });
        // Keep d2.* services available to the test container even when nothing uses them yet.
        $kernel->addTestCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'd2.')) {
                        $definition->setPublic(true);
                    }
                }
            }
        });
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testRendererIsRegisteredWithDefaultConfiguration(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(CachedRenderer::class, $container->get(Renderer::class));
        self::assertSame($container->get(Renderer::class), $container->get('d2.renderer'));
        self::assertInstanceOf(BinaryRenderer::class, $container->get('d2.renderer.binary'));
        self::assertInstanceOf(CacheItemPoolInterface::class, $container->get('cache.d2'));
        self::assertEquals(new RenderOptions(), $container->get('d2.default_options'));
    }

    public function testCacheCanBeDisabled(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', ['cache' => false]);
            });
        }]);

        self::assertInstanceOf(BinaryRenderer::class, self::getContainer()->get(Renderer::class));
    }

    public function testRenderedDiagramsAreCachedInTheD2Pool(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('The fake d2 binary is a POSIX shell script.');
        }

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', ['binary_path' => self::FAKE_D2_VERSION]);
            });
        }]);
        $container = self::getContainer();

        $pool = $container->get('cache.d2');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();

        $renderer = $container->get(Renderer::class);
        self::assertInstanceOf(Renderer::class, $renderer);

        $callsFile = tempnam(sys_get_temp_dir(), 'd2-calls-');
        self::assertIsString($callsFile);
        // Symfony Process passes $_ENV (not putenv() values) to child processes.
        $_ENV['D2_CALLS_FILE'] = $callsFile;

        try {
            $svg = $renderer->render(new Diagram('a -> b'));
            self::assertSame($svg, $renderer->render(new Diagram('a -> b')));

            // One call for the version, one for the first render; the second render is a cache hit.
            self::assertSame(['--version', '- -'], file($callsFile, \FILE_IGNORE_NEW_LINES));
        } finally {
            unset($_ENV['D2_CALLS_FILE']);
            unlink($callsFile);
        }
    }

    public function testRendererCanBeAutowired(): void
    {
        self::bootKernel();

        $consumer = self::getContainer()->get(RendererConsumer::class);

        self::assertInstanceOf(RendererConsumer::class, $consumer);
        self::assertInstanceOf(CachedRenderer::class, $consumer->renderer);
    }

    public function testConfigurationIsAppliedToServices(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('The fake d2 binary is a POSIX shell script.');
        }

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', [
                    'binary_path' => self::FAKE_D2,
                    'timeout' => 5,
                    'defaults' => ['theme' => 200, 'layout' => 'elk', 'pad' => 10, 'sketch' => true],
                ]);
            });
        }]);
        $container = self::getContainer();

        $renderer = $container->get(Renderer::class);
        self::assertInstanceOf(Renderer::class, $renderer);
        self::assertStringContainsString("stdin:a -> b\n", $renderer->render(new Diagram('a -> b')));

        self::assertEquals(
            new RenderOptions(theme: 200, layout: 'elk', pad: 10, sketch: true),
            $container->get('d2.default_options'),
        );
    }

    public function testInvalidConfigurationPreventsBoot(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', ['defaults' => ['layout' => 'elk; rm -rf /']]);
            });
        }]);
    }

    public function testTwigFilterRendersSvgThroughConfiguredServices(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('The fake d2 binary is a POSIX shell script.');
        }

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', [
                    'binary_path' => self::FAKE_D2,
                    'defaults' => ['theme' => 200],
                ]);
            });
        }]);

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $output = $twig->createTemplate("<main>{{ 'a -> b'|d2({ sketch: true }) }}</main>")->render();

        self::assertStringStartsWith('<main><svg>', $output);
        self::assertStringContainsString("arg:--theme=200\narg:--sketch\n", $output);
        self::assertStringContainsString("stdin:a -> b\n", $output);
    }

    public function testTwigTagRendersSvgThroughConfiguredServices(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('The fake d2 binary is a POSIX shell script.');
        }

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', ['binary_path' => self::FAKE_D2, 'cache' => false]);
            });
        }]);

        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $output = $twig->createTemplate("<main>{% d2 with { layout: 'elk' } %}user -> {{ target }}{% endd2 %}</main>")->render(['target' => 'app']);

        self::assertStringStartsWith('<main><svg>', $output);
        self::assertStringContainsString("arg:--layout=elk\n", $output);
        self::assertStringContainsString("stdin:user -> app\n", $output);
    }

    public function testStaticDiagramsNeverCallTheBinaryAfterWarmupInProduction(): void
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('The fake d2 binary is a POSIX shell script.');
        }

        self::bootKernel(['debug' => false, 'config' => static function (TestKernel $kernel): void {
            $kernel->addTestConfig(static function (ContainerBuilder $container): void {
                $container->loadFromExtension('d2', ['binary_path' => self::FAKE_D2_VERSION]);
                $container->loadFromExtension('twig', ['default_path' => self::TEMPLATES]);
            });
        }]);
        $container = self::getContainer();

        // Same as bin/console cache:warmup: optional warmers (like Twig templates) included.
        $warmer = $container->get('cache_warmer');
        self::assertInstanceOf(CacheWarmerAggregate::class, $warmer);
        $warmer->enableOptionalWarmers();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        $pool = $container->get('cache.d2');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();

        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $callsFile = tempnam(sys_get_temp_dir(), 'd2-calls-');
        self::assertIsString($callsFile);
        // Symfony Process passes $_ENV (not putenv() values) to child processes.
        $_ENV['D2_CALLS_FILE'] = $callsFile;

        try {
            for ($i = 0; $i < 3; ++$i) {
                self::assertStringContainsString('<svg>--sketch - -</svg>', $twig->render('static.html.twig'));
            }

            self::assertSame('', file_get_contents($callsFile), 'The d2 binary must not be called after warmup.');
        } finally {
            unset($_ENV['D2_CALLS_FILE']);
            unlink($callsFile);
        }
    }

    public function testStaticDiagramsAreNotPrerenderedInDebug(): void
    {
        self::bootKernel(['debug' => true]);

        $extension = self::getContainer()->get('d2.twig.extension');
        self::assertInstanceOf(D2Extension::class, $extension);
        self::assertSame([], $extension->getNodeVisitors());
    }
}
