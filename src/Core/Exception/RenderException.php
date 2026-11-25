<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when D2 fails to render a diagram (syntax error, layout failure, remote error…).
 *
 * The raw error reported by D2 is kept as-is in {@see getD2Error()}.
 */
final class RenderException extends \RuntimeException implements D2Exception
{
    public function __construct(
        string $message,
        private readonly string $d2Error = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Creates an exception from the raw error output of D2 (stderr, HTTP response body…).
     */
    public static function fromD2Error(string $d2Error, ?\Throwable $previous = null): self
    {
        $trimmed = trim($d2Error);
        $message = '' === $trimmed ? 'D2 rendering failed.' : 'D2 rendering failed: '.$trimmed;

        return new self($message, $d2Error, $previous);
    }

    /**
     * Returns the raw, unmodified error reported by D2.
     */
    public function getD2Error(): string
    {
        return $this->d2Error;
    }
}
