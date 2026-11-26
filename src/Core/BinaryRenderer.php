<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\BinaryNotFoundException;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Renders diagrams with a local d2 binary.
 *
 * The source is piped through stdin and the SVG read from stdout
 * (`d2 [options] - -`): no temporary file is written.
 */
final class BinaryRenderer implements VersionAwareRenderer
{
    private ?string $resolvedBinary = null;
    private ?string $version = null;

    /** @var non-empty-list<string> */
    private readonly array $binaries;

    /**
     * @param string|list<string> $binary       name of the d2 executable looked up in the PATH, or path to it;
     *                                          several candidates are tried in order
     * @param float|null          $timeout      maximum rendering time in seconds, null to disable
     * @param string|null         $notFoundHint how to install d2, shown when no candidate is found
     */
    public function __construct(
        string|array $binary = 'd2',
        private readonly ?float $timeout = 60.0,
        private readonly ?string $notFoundHint = null,
    ) {
        $binaries = \is_string($binary) ? [$binary] : $binary;
        if ([] === $binaries || \in_array('', $binaries, true)) {
            throw new InvalidArgumentException('The d2 binary must not be empty.');
        }
        $this->binaries = $binaries;

        if (null !== $timeout && $timeout <= 0) {
            throw new InvalidArgumentException(\sprintf('The timeout must be greater than 0 or null, %s given.', $timeout));
        }
    }

    public function render(Diagram $diagram): string
    {
        return $this->run([...$diagram->options->toCliArguments(), '-', '-'], $diagram->source);
    }

    /**
     * Returns the version reported by `d2 --version` (e.g. "v0.9.0"), computed once per instance.
     */
    public function getVersion(): string
    {
        return $this->version ??= trim($this->run(['--version']));
    }

    /**
     * Runs the d2 binary and returns its standard output.
     *
     * @param list<string> $arguments
     *
     * @return non-empty-string
     *
     * @throws RenderException
     * @throws BinaryNotFoundException
     */
    private function run(array $arguments, ?string $input = null): string
    {
        $process = new Process([$this->resolveBinary(), ...$arguments], null, null, $input, $this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RenderException(\sprintf('D2 rendering timed out after %s seconds.', $this->timeout), $process->getErrorOutput(), $e);
        } catch (ProcessRuntimeException $e) {
            throw $this->notFound($e);
        }

        $exitCode = $process->getExitCode();

        // 126: found but not executable, 127: not found (e.g. removed since resolution).
        if (126 === $exitCode || 127 === $exitCode) {
            throw $this->notFound();
        }

        if (0 !== $exitCode) {
            throw RenderException::fromD2Error($process->getErrorOutput());
        }

        $output = $process->getOutput();

        if ('' === trim($output)) {
            throw RenderException::fromD2Error($process->getErrorOutput());
        }

        return $output;
    }

    private function resolveBinary(): string
    {
        if (null !== $this->resolvedBinary) {
            return $this->resolvedBinary;
        }

        foreach ($this->binaries as $binary) {
            $resolved = self::find($binary);
            if (null !== $resolved) {
                return $this->resolvedBinary = $resolved;
            }
        }

        throw $this->notFound();
    }

    private static function find(string $binary): ?string
    {
        if (str_contains($binary, '/') || str_contains($binary, \DIRECTORY_SEPARATOR)) {
            return is_file($binary) && is_executable($binary) ? $binary : null;
        }

        return (new ExecutableFinder())->find($binary);
    }

    private function notFound(?\Throwable $previous = null): BinaryNotFoundException
    {
        return new BinaryNotFoundException($this->binaries, $previous, $this->notFoundHint);
    }
}
