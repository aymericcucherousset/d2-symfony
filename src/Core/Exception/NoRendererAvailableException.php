<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core\Exception;

/**
 * Thrown by {@see \Aymericcucherousset\D2Symfony\Core\ChainRenderer} when every renderer is unavailable.
 */
final class NoRendererAvailableException extends \RuntimeException implements RendererUnavailableException
{
    /**
     * @param non-empty-list<RendererUnavailableException> $failures the error of each renderer, in order
     */
    public function __construct(private readonly array $failures)
    {
        $messages = [];
        foreach ($failures as $i => $failure) {
            $messages[] = \sprintf('%d) %s', $i + 1, $failure->getMessage());
        }

        parent::__construct('No D2 renderer is available: '.implode(' ', $messages), 0, $failures[array_key_last($failures)]);
    }

    /**
     * Returns the error of each renderer, in the order they were tried.
     *
     * @return non-empty-list<RendererUnavailableException>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
