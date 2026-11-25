<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Integration\Bundle;

use Aymericcucherousset\D2Symfony\Bundle\D2Bundle;
use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\Bundle\RendererConsumer;
use Nyholm\BundleTest\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface;

final class D2BundleKernelTest extends KernelTestCase
{
    private const FAKE_D2 = __DIR__.'/../../Fixtures/bin/fake-d2-echo';

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

        $kernel->addTestBundle(D2Bundle::class);
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

        self::assertInstanceOf(BinaryRenderer::class, $container->get(Renderer::class));
        self::assertSame($container->get(Renderer::class), $container->get('d2.renderer'));
        self::assertEquals(new RenderOptions(), $container->get('d2.default_options'));
    }

    public function testRendererCanBeAutowired(): void
    {
        self::bootKernel();

        $consumer = self::getContainer()->get(RendererConsumer::class);

        self::assertInstanceOf(RendererConsumer::class, $consumer);
        self::assertInstanceOf(BinaryRenderer::class, $consumer->renderer);
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
}
