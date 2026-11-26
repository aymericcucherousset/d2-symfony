<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when D2 fails to render a diagram (syntax error, layout failure, remote error…).
 *
 * The raw error reported by D2 is kept as-is in {@see getD2Error()}; the location of
 * D2 errors ("-:LINE:COLUMN: message") is parsed and exposed by {@see getD2Line()},
 * {@see getD2Column()} and {@see getD2Errors()}.
 */
final class RenderException extends \RuntimeException implements D2Exception
{
    private const LOCATION_PATTERN = '/(?:^|\s)-:(\d+):(\d+):[ \t]*(.+?)[ \t]*$/m';

    /** @var list<array{line: int, column: int, message: string}> */
    private readonly array $d2Errors;

    public function __construct(
        string $message,
        private readonly string $d2Error = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);

        $this->d2Errors = self::parseErrors($d2Error);
    }

    /**
     * Creates an exception from the raw error output of D2 (stderr, HTTP response body…).
     */
    public static function fromD2Error(string $d2Error, ?\Throwable $previous = null): self
    {
        $errors = self::parseErrors($d2Error);

        if ([] !== $errors) {
            $message = \sprintf('D2 syntax error at line %d, column %d: %s', $errors[0]['line'], $errors[0]['column'], $errors[0]['message']);
            $others = \count($errors) - 1;
            if ($others > 0) {
                $message .= \sprintf(' (and %d more error%s)', $others, $others > 1 ? 's' : '');
            }
        } else {
            $trimmed = trim($d2Error);
            $message = '' === $trimmed ? 'D2 rendering failed.' : 'D2 rendering failed: '.$trimmed;
        }

        return new self($message, $d2Error, $previous);
    }

    /**
     * Returns the raw, unmodified error reported by D2.
     */
    public function getD2Error(): string
    {
        return $this->d2Error;
    }

    /**
     * Returns the line (1-based, in the D2 source) of the first error, if D2 reported one.
     */
    public function getD2Line(): ?int
    {
        return $this->d2Errors[0]['line'] ?? null;
    }

    /**
     * Returns the column (1-based) of the first error, if D2 reported one.
     */
    public function getD2Column(): ?int
    {
        return $this->d2Errors[0]['column'] ?? null;
    }

    /**
     * Returns every located error reported by D2, in order.
     *
     * @return list<array{line: int, column: int, message: string}>
     */
    public function getD2Errors(): array
    {
        return $this->d2Errors;
    }

    /**
     * @return list<array{line: int, column: int, message: string}>
     */
    private static function parseErrors(string $d2Error): array
    {
        if (false === preg_match_all(self::LOCATION_PATTERN, $d2Error, $matches, \PREG_SET_ORDER)) {
            return [];
        }

        return array_map(static fn (array $match): array => [
            'line' => (int) $match[1],
            'column' => (int) $match[2],
            'message' => $match[3],
        ], $matches);
    }
}
