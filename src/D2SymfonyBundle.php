<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony;

use Aymericcucherousset\D2Symfony\Command\DownloadCommand;
use Aymericcucherousset\D2Symfony\Command\RenderCommand;
use Aymericcucherousset\D2Symfony\CommonMark\D2CommonMarkExtension;
use Aymericcucherousset\D2Symfony\Core\BinaryRenderer;
use Aymericcucherousset\D2Symfony\Core\CachedRenderer;
use Aymericcucherousset\D2Symfony\Core\ChainRenderer;
use Aymericcucherousset\D2Symfony\Core\KrokiRenderer;
use Aymericcucherousset\D2Symfony\Core\Layout;
use Aymericcucherousset\D2Symfony\Core\Renderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Downloader\BinaryDownloader;
use Aymericcucherousset\D2Symfony\Profiler\D2DataCollector;
use Aymericcucherousset\D2Symfony\Profiler\RemoveProfilerServicesPass;
use Aymericcucherousset\D2Symfony\Profiler\RenderTracer;
use Aymericcucherousset\D2Symfony\Profiler\TraceableBackendRenderer;
use Aymericcucherousset\D2Symfony\Profiler\TraceableRenderer;
use Aymericcucherousset\D2Symfony\Twig\D2Extension;
use Aymericcucherousset\D2Symfony\Twig\D2FileLoader;
use Aymericcucherousset\D2Symfony\Twig\D2Runtime;
use League\CommonMark\Extension\ExtensionInterface as CommonMarkExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * @phpstan-type D2Config array{
 *     renderer: 'binary'|'kroki'|'chain',
 *     chain: non-empty-list<'binary'|'kroki'>,
 *     kroki: array{base_url: string, http_client: string},
 *     binary_path: string|null,
 *     binary_version: string,
 *     install_dir: string,
 *     timeout: int|float|null,
 *     defaults: array{
 *         theme: int|null,
 *         dark_theme: int|null,
 *         layout: string|null,
 *         pad: int|null,
 *         sketch: bool,
 *         center: bool,
 *         scale: float|null,
 *         font_regular: string|null,
 *         font_italic: string|null,
 *         font_bold: string|null,
 *         font_semibold: string|null,
 *     },
 *     cache: array{enabled: bool, pool: string},
 *     twig: array{prerender_static: bool|null, root_dir: string},
 * }
 */
final class D2SymfonyBundle extends AbstractBundle
{
    private const CACHE_POOL = 'cache.d2';
    private const RENDERERS = ['binary', 'kroki'];

    protected string $extensionAlias = 'd2';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('renderer')
                    ->info('How diagrams are rendered: "binary" (local d2), "kroki" (Kroki server over HTTP) or "chain" (each renderer of "chain" in order).')
                    ->values([...self::RENDERERS, 'chain'])
                    ->defaultValue('binary')
                ->end()
                ->arrayNode('chain')
                    ->info('Renderers tried in order when "renderer" is "chain"; the next one is used only when the previous one is unavailable (missing binary, unreachable server).')
                    ->enumPrototype()->values(self::RENDERERS)->end()
                    ->defaultValue(self::RENDERERS)
                    ->requiresAtLeastOneElement()
                    ->validate()
                        ->ifTrue(static fn (array $chain): bool => \count($chain) !== \count(array_unique($chain, \SORT_REGULAR)))
                        ->thenInvalid('Each renderer must appear only once in the chain, %s given.')
                    ->end()
                ->end()
                ->arrayNode('kroki')
                    ->info('Kroki server used by the "kroki" renderer.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base_url')
                            ->info('URL of the Kroki server: the public instance or a self-hosted one.')
                            ->defaultValue(KrokiRenderer::DEFAULT_BASE_URL)
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || !\in_array(strtolower((string) parse_url($value, \PHP_URL_SCHEME)), ['http', 'https'], true) || '' === (string) parse_url($value, \PHP_URL_HOST))
                                ->thenInvalid('The Kroki base URL must be an absolute http(s) URL, %s given.')
                            ->end()
                        ->end()
                        ->scalarNode('http_client')
                            ->info('HTTP client service ID, e.g. a scoped client; a default client is created when the service does not exist.')
                            ->defaultValue('http_client')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('binary_path')
                    ->info('Name of the d2 executable looked up in the PATH, or path to it; null uses the binary installed by d2:download, then "d2" from the PATH.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => null !== $value && (!\is_string($value) || '' === $value))
                        ->thenInvalid('The d2 binary path must be a non-empty string or null, %s given.')
                    ->end()
                ->end()
                ->scalarNode('binary_version')
                    ->info('Version of d2 installed by the d2:download command.')
                    ->defaultValue(BinaryDownloader::DEFAULT_VERSION)
                    ->validate()
                        ->ifTrue(static fn (mixed $value): bool => !\is_string($value) || 1 !== preg_match('/^v?\d+\.\d+\.\d+$/', $value))
                        ->thenInvalid('The d2 version must look like "v0.9.0", %s given.')
                    ->end()
                ->end()
                ->scalarNode('install_dir')
                    ->info('Directory where d2:download installs the binary.')
                    ->defaultValue('%kernel.project_dir%/var/d2')
                    ->cannotBeEmpty()
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
                        // Theme and Layout enums are accepted in PHP configuration files.
                        ->then(static fn (array $options): array => array_map(
                            static fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value,
                            array_filter($options, static fn (mixed $value): bool => null !== $value),
                        ))
                    ->end()
                    ->children()
                        ->integerNode('theme')->info('d2 theme ID, e.g. 200 (Dark Mauve); see the Theme enum or "d2 themes".')->defaultNull()->min(0)->end()
                        ->integerNode('dark_theme')->info('d2 theme ID used when the viewer\'s browser is in dark mode; null uses "theme" in both modes.')->defaultNull()->min(0)->end()
                        ->enumNode('layout')->info('Layout engine ("tala" requires the TALA plugin).')->values([...Layout::values(), null])->defaultNull()->end()
                        ->integerNode('pad')->info('Padding around the diagram, in pixels.')->defaultNull()->min(0)->end()
                        ->booleanNode('sketch')->info('Render with a hand-drawn look.')->defaultFalse()->end()
                        ->booleanNode('center')->info('Center the SVG in its viewbox (local binary only).')->defaultFalse()->end()
                        ->floatNode('scale')->info('Scale factor, e.g. 0.5 halves the size; null fits SVG to the screen.')->defaultNull()->end()
                        ->scalarNode('font_regular')->info('Path to the .ttf file used for regular text (local binary only).')->defaultNull()->end()
                        ->scalarNode('font_italic')->info('Path to the .ttf file used for italic text (local binary only).')->defaultNull()->end()
                        ->scalarNode('font_bold')->info('Path to the .ttf file used for bold text (local binary only).')->defaultNull()->end()
                        ->scalarNode('font_semibold')->info('Path to the .ttf file used for semibold text (local binary only).')->defaultNull()->end()
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
                ->arrayNode('twig')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('prerender_static')
                            ->info('Render {% d2 %} tags with a constant body when templates are compiled (cache:warmup); null enables it when kernel.debug is false.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => null !== $value && !\is_bool($value))
                                ->thenInvalid('The prerender_static option must be a boolean or null, %s given.')
                            ->end()
                        ->end()
                        ->scalarNode('root_dir')
                            ->info('Directory that d2_file() paths are relative to; files outside of it cannot be rendered.')
                            ->defaultValue('%kernel.project_dir%')
                            ->cannotBeEmpty()
                            ->validate()
                                ->ifTrue(static fn (mixed $value): bool => !\is_string($value))
                                ->thenInvalid('The root_dir option must be a directory path, %s given.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveProfilerServicesPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
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

        $services->set('d2.binary_downloader', BinaryDownloader::class)
            ->args([
                $config['install_dir'],
                $config['binary_version'],
                service('http_client')->nullOnInvalid(),
            ]);

        $services->set('d2.command.download', DownloadCommand::class)
            ->args([service('d2.binary_downloader')])
            ->tag('console.command', ['command' => 'd2:download']);

        // An explicit binary_path wins; otherwise use the downloaded binary, then "d2" from the PATH.
        $binaries = null !== $config['binary_path'] ? [$config['binary_path']] : [
            \sprintf('%s/v%s/%s', rtrim($config['install_dir'], '/\\'), ltrim($config['binary_version'], 'v'), '\\' === \DIRECTORY_SEPARATOR ? 'd2.exe' : 'd2'),
            'd2',
        ];

        $services->set('d2.renderer.binary', BinaryRenderer::class)
            ->args([
                $binaries,
                null === $config['timeout'] ? null : (float) $config['timeout'],
                'Run "bin/console d2:download" to install it, or set "d2.binary_path".',
            ]);

        $renderers = 'chain' === $config['renderer'] ? $config['chain'] : [$config['renderer']];

        if (\in_array('kroki', $renderers, true)) {
            $services->set('d2.kroki.http_client', HttpClientInterface::class)
                ->factory([self::class, 'createHttpClient'])
                ->args([service($config['kroki']['http_client'])->nullOnInvalid()]);

            $services->set('d2.renderer.kroki', KrokiRenderer::class)
                ->args([
                    service('d2.kroki.http_client'),
                    $config['kroki']['base_url'],
                    null === $config['timeout'] ? null : (float) $config['timeout'],
                    service('logger')->nullOnInvalid(),
                ])
                ->tag('monolog.logger', ['channel' => 'd2']);
        }

        if ('chain' === $config['renderer']) {
            $services->set('d2.renderer.chain', ChainRenderer::class)
                ->args([array_map(static fn (string $name) => service('d2.renderer.'.$name), $config['chain'])]);
        }

        $services->alias('d2.renderer.inner', 'd2.renderer.'.$config['renderer']);

        if ($config['cache']['enabled']) {
            $services->set('d2.renderer.cached', CachedRenderer::class)
                ->args([service('d2.renderer.inner'), service($config['cache']['pool'])]);

            $services->alias('d2.renderer', 'd2.renderer.cached');
        } else {
            $services->alias('d2.renderer', 'd2.renderer.inner');
        }
        $services->alias(Renderer::class, 'd2.renderer');

        if ($builder->getParameter('kernel.debug')) {
            $this->registerProfilerServices($services, $renderers, $config['cache']['enabled']);
        }

        $defaults = $config['defaults'];
        $services->set('d2.default_options', RenderOptions::class)
            ->args([
                '$theme' => $defaults['theme'],
                '$layout' => $defaults['layout'],
                '$pad' => $defaults['pad'],
                '$sketch' => $defaults['sketch'],
                '$darkTheme' => $defaults['dark_theme'],
                '$center' => $defaults['center'],
                '$scale' => null === $defaults['scale'] ? null : (float) $defaults['scale'],
                '$fontRegular' => $defaults['font_regular'],
                '$fontItalic' => $defaults['font_italic'],
                '$fontBold' => $defaults['font_bold'],
                '$fontSemibold' => $defaults['font_semibold'],
            ]);

        $services->set('d2.command.render', RenderCommand::class)
            ->args([service('d2.renderer'), service('d2.default_options')])
            ->tag('console.command', ['command' => 'd2:render']);

        if (ContainerBuilder::willBeAvailable('twig/twig', Environment::class, ['aymericcucherousset/d2-symfony'])) {
            $services->set('d2.twig.extension', D2Extension::class)
                ->args([$config['twig']['prerender_static'] ?? !$builder->getParameter('kernel.debug')])
                ->tag('twig.extension');

            $services->set('d2.twig.file_loader', D2FileLoader::class)
                ->args([$config['twig']['root_dir']]);

            $services->set('d2.twig.runtime', D2Runtime::class)
                ->args([service('d2.renderer'), service('d2.default_options'), service('d2.twig.file_loader')])
                ->tag('twig.runtime');
        }

        if (ContainerBuilder::willBeAvailable('league/commonmark', CommonMarkExtension::class, ['aymericcucherousset/d2-symfony'])) {
            // The tag adds it to the converter of the |markdown_to_html filter (twig/extra-bundle).
            $services->set('d2.commonmark.extension', D2CommonMarkExtension::class)
                ->args([service('d2.renderer'), service('d2.default_options')])
                ->tag('twig.markdown.league_extension');
            $services->alias(D2CommonMarkExtension::class, 'd2.commonmark.extension');
        }
    }

    /**
     * Returns the configured HTTP client, or a default one when the service does not exist (no FrameworkBundle).
     *
     * @internal
     */
    public static function createHttpClient(?HttpClientInterface $client = null): HttpClientInterface
    {
        return $client ?? HttpClient::create();
    }

    /**
     * Traces renders for the Web Profiler; removed by {@see RemoveProfilerServicesPass} when the profiler is disabled.
     *
     * @param list<'binary'|'kroki'> $renderers
     */
    private function registerProfilerServices(ServicesConfigurator $services, array $renderers, bool $cached): void
    {
        $prefix = RemoveProfilerServicesPass::PREFIX;

        $services->set($prefix.'tracer', RenderTracer::class)
            ->tag('kernel.reset', ['method' => 'reset']);

        foreach ($renderers as $name) {
            $services->set($prefix.'renderer.'.$name, TraceableBackendRenderer::class)
                ->decorate('d2.renderer.'.$name)
                ->args([service('.inner'), service($prefix.'tracer'), $name]);
        }

        $services->set($prefix.'renderer', TraceableRenderer::class)
            ->decorate('d2.renderer')
            ->args([service('.inner'), service($prefix.'tracer'), $cached, service('debug.stopwatch')->nullOnInvalid()]);

        $services->set($prefix.'data_collector', D2DataCollector::class)
            ->args([service($prefix.'tracer')])
            ->tag('data_collector', ['id' => 'd2', 'template' => '@D2Symfony/Collector/d2.html.twig']);
    }

    private static function createRenderOptions(mixed $options): RenderOptions
    {
        if (!\is_array($options)) {
            throw new \InvalidArgumentException('The default render options must be an array.');
        }

        return (new RenderOptions())->merge($options);
    }
}
