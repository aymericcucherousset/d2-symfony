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

    /**
     * @param string     $binary  name of the d2 executable looked up in the PATH, or path to it
     * @param float|null $timeout maximum rendering time in seconds, null to disable
     */
    public function __construct(
        private readonly string $binary = 'd2',
        private readonly ?float $timeout = 60.0,
    ) {
        if ('' === $binary) {
            throw new InvalidArgumentException('The d2 binary must not be empty.');
        }

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
            throw new BinaryNotFoundException($this->binary, $e);
        }

        $exitCode = $process->getExitCode();

        // 126: found but not executable, 127: not found (e.g. removed since resolution).
        if (126 === $exitCode || 127 === $exitCode) {
            throw new BinaryNotFoundException($this->binary);
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

        if (str_contains($this->binary, '/') || str_contains($this->binary, \DIRECTORY_SEPARATOR)) {
            if (!is_file($this->binary) || !is_executable($this->binary)) {
                throw new BinaryNotFoundException($this->binary);
            }

            return $this->resolvedBinary = $this->binary;
        }

        return $this->resolvedBinary = (new ExecutableFinder())->find($this->binary)
            ?? throw new BinaryNotFoundException($this->binary);
    }
}
