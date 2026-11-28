<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Profiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes the D2 profiler services when the Symfony profiler is disabled, so renderers are not decorated for nothing.
 *
 * Runs before service decoration is resolved.
 *
 * @internal
 */
final class RemoveProfilerServicesPass implements CompilerPassInterface
{
    public const PREFIX = 'd2.profiler.';

    public function process(ContainerBuilder $container): void
    {
        if ($container->has('profiler')) {
            return;
        }

        foreach (array_keys($container->getDefinitions()) as $id) {
            if (str_starts_with($id, self::PREFIX)) {
                $container->removeDefinition($id);
            }
        }
    }
}
