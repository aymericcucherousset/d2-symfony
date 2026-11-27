<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Fixtures\Bundle;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds an HTTP client answering like Kroki, echoing the URL and the diagram source.
 */
final class FakeKrokiClient
{
    public static function create(): HttpClientInterface
    {
        return new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';

            return new MockResponse(\sprintf('<svg data-url="%s">%s</svg>', $url, \is_string($body) ? $body : ''));
        });
    }
}
