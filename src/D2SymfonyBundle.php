<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony;

use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @phpstan-type D2Config array{
 *     binary_path: string,
 *     timeout: int|float|null,
 *     defaults: array{theme: int|null, layout: string|null, pad: int|null, sketch: bool},
 *     cache: array{enabled: bool, pool: string},
 * }
 */
final class D2SymfonyBundle extends AbstractBundle
{
    private const CACHE_POOL = 'cache.d2';

    protected string $extensionAlias = 'd2';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('binary_path')
                    ->info('Name of the d2 executable looked up in the PATH, or path to it.')
                    ->defaultValue('d2')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !\is_string($value))
                        ->thenInvalid('The d2 binary path must be a string, %s given.')
                    ->end()
                ->end()
                ->scalarNode('timeout')
                    ->info('Maximum rendering time in seconds; null disables the limit.')
                    ->defaultValue(60.0)
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => null !== $value && (!(\is_int($value) || \is_float($value)) || $value <= 0))
                        ->thenInvalid('The timeout must be a number greater than 0 or null, %s given.')
                    ->end()
                ->end()
                ->arrayNode('defaults')
                    ->info('Render options applied to every diagram unless overridden; null means "use the d2 default".')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static fn (array $options): array => array_filter($options, static fn (mixed $value): bool => null !== $value))
                    ->end()
                    ->children()
                        ->integerNode('theme')->info('d2 theme ID.')->defaultNull()->min(0)->end()
                        ->scalarNode('layout')->info('Layout engine, e.g. "dagre" or "elk".')->defaultNull()->end()
                        ->integerNode('pad')->info('Padding around the diagram, in pixels.')->defaultNull()->min(0)->end()
                        ->booleanNode('sketch')->info('Render with a hand-drawn look.')->defaultFalse()->end()
                    ->end()
                    ->validate()
                        ->always(static function (mixed $options): mixed {
                            // Reuse the core validation rules as the single source of truth.
                            self::createRenderOptions($options);

                            return $options;
                        })
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->info('Cache rendered SVG so each diagram is rendered by d2 only once.')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('pool')
                            ->info('PSR-6 cache pool service; "cache.d2" is created automatically with FrameworkBundle.')
                            ->defaultValue(self::CACHE_POOL)
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => !\is_string($value))
                                ->thenInvalid('The cache pool must be a service ID, %s given.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'cache' => ['pools' => [self::CACHE_POOL => ['adapter' => 'cache.app']]],
            ]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var D2Config $config */
        $services = $container->services();

        $services->set('d2.renderer.binary', BinaryRenderer::class)
            ->args([
                $config['binary_path'],
                null === $config['timeout'] ? null : (float) $config['timeout'],
            ]);

        if ($config['cache']['enabled']) {
            $services->set('d2.renderer.cached', CachedRenderer::class)
                ->args([service('d2.renderer.binary'), service($config['cache']['pool'])]);

            $services->alias('d2.renderer', 'd2.renderer.cached');
        } else {
            $services->alias('d2.renderer', 'd2.renderer.binary');
        }
        $services->alias(Renderer::class, 'd2.renderer');

        $defaults = $config['defaults'];
        $services->set('d2.default_options', RenderOptions::class)
            ->args([$defaults['theme'], $defaults['layout'], $defaults['pad'], $defaults['sketch']]);

        if (ContainerBuilder::willBeAvailable('twig/twig', Environment::class, ['aymericcucherousset/d2-symfony'])) {
            $services->set('d2.twig.extension', D2Extension::class)
                ->tag('twig.extension');

            $services->set('d2.twig.runtime', D2Runtime::class)
                ->args([service('d2.renderer'), service('d2.default_options')])
                ->tag('twig.runtime');
        }
    }

    private static function createRenderOptions(mixed $options): RenderOptions
    {
        if (!\is_array($options)) {
            throw new \InvalidArgumentException('The default render options must be an array.');
        }

        return (new RenderOptions())->merge($options);
    }
}
