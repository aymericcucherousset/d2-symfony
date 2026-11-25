<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Bundle;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Reproduces how Symfony Flex finds the bundle class of a "symfony-bundle" package
 * (symfony/flex SymfonyBundle::extractClassNames() and isBundleClass()): for each PSR-4
 * namespace, it takes the last namespace segment, appends "Bundle" and looks for that
 * file at the root of the mapped directory.
 *
 * If this test fails, Flex will no longer register the bundle automatically.
 */
final class FlexDiscoveryTest extends TestCase
{
    private const PACKAGE_ROOT = __DIR__.'/../../..';

    public function testFlexCanGuessTheBundleClassFromThePsr4Namespace(): void
    {
        $composer = json_decode((string) file_get_contents(self::PACKAGE_ROOT.'/composer.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('symfony-bundle', $composer['type'] ?? null);

        $namespace = $this->findNamespaceMappedTo($composer, 'src/');
        $segments = explode('\\', rtrim($namespace, '\\'));
        $shortName = end($segments).'Bundle';
        $class = $namespace.$shortName;

        self::assertFileExists(self::PACKAGE_ROOT.'/src/'.$shortName.'.php', \sprintf('Flex looks for the bundle class "%s" in "src/%s.php".', $class, $shortName));
        self::assertTrue(class_exists($class), \sprintf('The class "%s" does not exist.', $class));
        self::assertTrue(is_a($class, AbstractBundle::class, true), \sprintf('The class "%s" must extend "%s".', $class, AbstractBundle::class));
        self::assertFalse((new \ReflectionClass($class))->isAbstract(), \sprintf('The class "%s" must not be abstract.', $class));
    }

    /**
     * @param array<mixed> $composer
     */
    private function findNamespaceMappedTo(array $composer, string $directory): string
    {
        $autoload = $composer['autoload'] ?? null;
        self::assertIsArray($autoload, 'composer.json must declare an autoload section.');
        $psr4 = $autoload['psr-4'] ?? null;
        self::assertIsArray($psr4, 'composer.json must declare a PSR-4 autoload section.');

        foreach ($psr4 as $namespace => $paths) {
            foreach ((array) $paths as $path) {
                if (\is_string($path) && rtrim($path, '/').'/' === $directory) {
                    return (string) $namespace;
                }
            }
        }

        self::fail(\sprintf('No PSR-4 namespace is mapped to "%s" in composer.json.', $directory));
    }
}
