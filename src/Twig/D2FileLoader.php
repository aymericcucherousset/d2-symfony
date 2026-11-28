<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Twig;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;

/**
 * Reads `.d2` files located under a root directory, for the `d2_file()` Twig function.
 *
 * Paths are relative to the root directory. Paths escaping it (`../`, absolute paths,
 * symbolic links pointing outside of it) and files without the `.d2` extension are rejected.
 */
final readonly class D2FileLoader
{
    private string $rootDir;

    public function __construct(string $rootDir)
    {
        if ('' === $rootDir) {
            throw new InvalidArgumentException('The D2 files root directory must not be empty.');
        }

        $this->rootDir = rtrim($rootDir, '/\\');
    }

    public function getRootDir(): string
    {
        return $this->rootDir;
    }

    /**
     * Returns the D2 source of the file at $path, relative to the root directory.
     *
     * @throws InvalidArgumentException when the path is invalid, outside the root directory, or the file cannot be read
     */
    public function load(string $path): string
    {
        if ('' === $path || str_contains($path, "\0")) {
            throw new InvalidArgumentException('The D2 file path must be a non-empty string.');
        }

        if (self::isAbsolute($path)) {
            throw new InvalidArgumentException(\sprintf('The D2 file path "%s" must be relative to "%s".', $path, $this->rootDir));
        }

        if ('d2' !== strtolower(pathinfo($path, \PATHINFO_EXTENSION))) {
            throw new InvalidArgumentException(\sprintf('The D2 file "%s" must have the ".d2" extension.', $path));
        }

        $root = realpath($this->rootDir);
        if (false === $root || !is_dir($root)) {
            throw new InvalidArgumentException(\sprintf('The D2 files root directory "%s" does not exist.', $this->rootDir));
        }

        // realpath() resolves "..", "." and symbolic links, so the check below cannot be bypassed.
        $file = realpath($root.\DIRECTORY_SEPARATOR.$path);
        if (false !== $file && !str_starts_with($file, rtrim($root, '/\\').\DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(\sprintf('The D2 file "%s" is outside of "%s".', $path, $this->rootDir));
        }

        if (false === $file || !is_file($file)) {
            throw new InvalidArgumentException(\sprintf('The D2 file "%s" does not exist in "%s".', $path, $this->rootDir));
        }

        $source = @file_get_contents($file);
        if (false === $source) {
            throw new InvalidArgumentException(\sprintf('Unable to read the D2 file "%s": %s', $path, error_get_last()['message'] ?? 'unknown error'));
        }

        return $source;
    }

    private static function isAbsolute(string $path): bool
    {
        return '/' === $path[0] || '\\' === $path[0] || 1 === preg_match('#^[a-zA-Z]:|^[a-zA-Z][a-zA-Z0-9+.-]*://#', $path);
    }
}
