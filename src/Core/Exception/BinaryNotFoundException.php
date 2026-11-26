<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when the d2 binary is missing or cannot be executed.
 */
final class BinaryNotFoundException extends \RuntimeException implements D2Exception
{
    public const DEFAULT_HINT = 'Install it from https://d2lang.com/tour/install or configure its path.';

    /** @var non-empty-list<string> */
    private readonly array $candidates;

    /**
     * @param string|non-empty-list<string> $binaries the binary, or every candidate that was tried
     * @param string|null                   $hint     how to fix the problem
     */
    public function __construct(
        string|array $binaries,
        ?\Throwable $previous = null,
        ?string $hint = null,
    ) {
        $this->candidates = \is_string($binaries) ? [$binaries] : $binaries;

        $message = 1 === \count($this->candidates)
            ? \sprintf('The d2 binary "%s" was not found or is not executable.', $this->candidates[0])
            : \sprintf('The d2 binary was not found or is not executable (tried "%s").', implode('", "', $this->candidates));

        parent::__construct($message.' '.($hint ?? self::DEFAULT_HINT), 0, $previous);
    }

    /**
     * Returns the binary that was looked for (the first candidate when several were tried).
     */
    public function getBinary(): string
    {
        return $this->candidates[0];
    }

    /**
     * @return non-empty-list<string>
     */
    public function getCandidates(): array
    {
        return $this->candidates;
    }
}
