<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Core;

use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Renders diagrams over HTTP with a Kroki server (https://kroki.io or a self-hosted instance).
 *
 * Useful where running binaries is forbidden. The source is sent as the body of
 * `POST {baseUrl}/d2/svg`; render options are sent as `Kroki-Diagram-Options-*` headers.
 * Options Kroki does not support are ignored, with a warning logged.
 */
final class KrokiRenderer implements VersionAwareRenderer
{
    public const DEFAULT_BASE_URL = 'https://kroki.io';

    private const SUPPORTED_OPTIONS = ['theme', 'layout', 'pad', 'sketch'];

    private readonly string $baseUrl;

    /**
     * @param string     $baseUrl URL of the Kroki server, optionally with a path prefix (e.g. "https://example.com/kroki")
     * @param float|null $timeout maximum rendering time in seconds, null to use the HTTP client default
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly ?float $timeout = 60.0,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $scheme = parse_url($baseUrl, \PHP_URL_SCHEME);
        $host = parse_url($baseUrl, \PHP_URL_HOST);
        if (!\in_array(\is_string($scheme) ? strtolower($scheme) : null, ['http', 'https'], true) || !\is_string($host) || '' === $host) {
            throw new InvalidArgumentException(\sprintf('The Kroki base URL must be an absolute http(s) URL, "%s" given.', $baseUrl));
        }
        $this->baseUrl = rtrim($baseUrl, '/');

        if (null !== $timeout && $timeout <= 0) {
            throw new InvalidArgumentException(\sprintf('The timeout must be greater than 0 or null, %s given.', $timeout));
        }
    }

    public function render(Diagram $diagram): string
    {
        $url = $this->baseUrl.'/d2/svg';
        $options = [
            'headers' => [
                'Content-Type' => 'text/plain',
                'Accept' => 'image/svg+xml',
                ...$this->optionHeaders($diagram->options),
            ],
            'body' => $diagram->source,
        ];

        if (null !== $this->timeout) {
            $options['timeout'] = $this->timeout;
            $options['max_duration'] = $this->timeout;
        }

        try {
            $response = $this->httpClient->request('POST', $url, $options);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            throw new RenderException(\sprintf('The Kroki request to "%s" failed: %s', $url, $e->getMessage()), '', $e);
        }

        if ($statusCode >= 300) {
            if ('' === trim($body)) {
                throw new RenderException(\sprintf('Kroki returned HTTP %d.', $statusCode));
            }

            throw RenderException::fromD2Error($body);
        }

        if ('' === trim($body)) {
            throw new RenderException('Kroki returned an empty response.');
        }

        return $body;
    }

    /**
     * Identifies the Kroki server, so cached diagrams are not shared with other renderers or servers.
     *
     * Kroki does not report the d2 version it runs, so no request is made.
     */
    public function getVersion(): string
    {
        return 'kroki:'.$this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    private function optionHeaders(RenderOptions $options): array
    {
        $headers = [];

        foreach ($options->toArray() as $name => $value) {
            if (!\in_array($name, self::SUPPORTED_OPTIONS, true)) {
                $this->logger->warning('The D2 option "{option}" is not supported by Kroki and is ignored.', ['option' => $name]);

                continue;
            }

            $headers['Kroki-Diagram-Options-'.$name] = true === $value ? 'true' : (string) $value;
        }

        return $headers;
    }
}
