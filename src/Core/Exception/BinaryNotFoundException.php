<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown when the d2 binary is missing or cannot be executed.
 */
final class BinaryNotFoundException extends \RuntimeException implements D2Exception
{
    public function __construct(
        private readonly string $binary,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf(
            'The d2 binary "%s" was not found or is not executable. Install it from https://d2lang.com/tour/install or configure its path.',
            $binary,
        ), 0, $previous);
    }

    public function getBinary(): string
    {
        return $this->binary;
    }
}
