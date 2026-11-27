<?php

declare(strict_types=1);

namespace Aymericcucherousset\D2Symfony\Tests\Unit\Core;

use Aymericcucherousset\D2Symfony\Core\Diagram;
use Aymericcucherousset\D2Symfony\Core\Exception\InvalidArgumentException;
use Aymericcucherousset\D2Symfony\Core\Exception\RenderException;
use Aymericcucherousset\D2Symfony\Core\KrokiRenderer;
use Aymericcucherousset\D2Symfony\Core\RenderOptions;
use Aymericcucherousset\D2Symfony\Tests\Fixtures\SpyLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class KrokiRendererTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

    public function testItPostsTheSourceAndReturnsTheSvg(): void
    {
        $response = new MockResponse(self::SVG);
        $renderer = new KrokiRenderer(new MockHttpClient($response));

        self::assertSame(self::SVG, $renderer->render(new Diagram('a -> b')));
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://kroki.io/d2/svg', $response->getRequestUrl());
        self::assertSame('a -> b', $response->getRequestOptions()['body']);

        $headers = self::headers($response);
        self::assertSame('text/plain', $headers['content-type']);
        self::assertSame('image/svg+xml', $headers['accept']);
        self::assertSame([], array_filter(array_keys($headers), static fn (string $name): bool => str_starts_with($name, 'kroki-')));
    }

    public function testOptionsAreSentAsKrokiHeaders(): void
    {
        $response = new MockResponse(self::SVG);
        $logger = new SpyLogger();
        $renderer = new KrokiRenderer(new MockHttpClient($response), logger: $logger);

        $renderer->render(new Diagram('a -> b', new RenderOptions(theme: 200, layout: 'elk', pad: 0, sketch: true)));

        $headers = self::headers($response);
        self::assertSame('200', $headers['kroki-diagram-options-theme']);
        self::assertSame('elk', $headers['kroki-diagram-options-layout']);
        self::assertSame('0', $headers['kroki-diagram-options-pad']);
        self::assertSame('true', $headers['kroki-diagram-options-sketch']);
        self::assertSame([], $logger->logs, 'Supported options trigger no warning.');
    }

    public function testCustomBaseUrlWithPathAndTrailingSlash(): void
    {
        $response = new MockResponse(self::SVG);
        $renderer = new KrokiRenderer(new MockHttpClient($response), 'https://kroki.example.com/kroki/');

        $renderer->render(new Diagram('a -> b'));

        self::assertSame('https://kroki.example.com/kroki/d2/svg', $response->getRequestUrl());
        self::assertSame('kroki:https://kroki.example.com/kroki', $renderer->getVersion());
    }

    public function testTimeoutIsForwarded(): void
    {
        $response = new MockResponse(self::SVG);
        (new KrokiRenderer(new MockHttpClient($response), timeout: 2.5))->render(new Diagram('a -> b'));

        self::assertSame(2.5, $response->getRequestOptions()['timeout']);
        self::assertSame(2.5, $response->getRequestOptions()['max_duration']);
    }

    public function testNullTimeoutKeepsTheClientDefaults(): void
    {
        $response = new MockResponse(self::SVG);
        $client = (new MockHttpClient($response))->withOptions(['timeout' => 7.0]);
        (new KrokiRenderer($client, timeout: null))->render(new Diagram('a -> b'));

        self::assertSame(7.0, $response->getRequestOptions()['timeout']);
    }

    public function testD2ErrorsReturnedByKrokiAreParsed(): void
    {
        $body = "err: failed to compile -: -:2:4: maps must be terminated with }\n (exit code 1)";
        $renderer = new KrokiRenderer(new MockHttpClient(new MockResponse($body, ['http_code' => 400])));

        try {
            $renderer->render(new Diagram("a: {\n  b"));
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException $e) {
            self::assertSame(2, $e->getD2Line());
            self::assertSame(4, $e->getD2Column());
            self::assertSame($body, $e->getD2Error());
            self::assertStringContainsString('maps must be terminated with }', $e->getMessage());
        }
    }

    public function testHttpErrorWithoutBody(): void
    {
        $renderer = new KrokiRenderer(new MockHttpClient(new MockResponse('', ['http_code' => 503])));

        $this->expectException(RenderException::class);
        $this->expectExceptionMessage('Kroki returned HTTP 503.');

        $renderer->render(new Diagram('a -> b'));
    }

    public function testTransportErrorsAreWrapped(): void
    {
        $renderer = new KrokiRenderer(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused'])));

        try {
            $renderer->render(new Diagram('a -> b'));
            self::fail('A RenderException should have been thrown.');
        } catch (RenderException $e) {
            self::assertStringContainsString('The Kroki request to "https://kroki.io/d2/svg" failed', $e->getMessage());
            self::assertStringContainsString('Connection refused', $e->getMessage());
            self::assertInstanceOf(TransportExceptionInterface::class, $e->getPrevious());
        }
    }

    public function testEmptySuccessfulResponseIsAnError(): void
    {
        $renderer = new KrokiRenderer(new MockHttpClient(new MockResponse(" \n")));

        $this->expectException(RenderException::class);
        $this->expectExceptionMessage('Kroki returned an empty response.');

        $renderer->render(new Diagram('a -> b'));
    }

    public function testGetVersionMakesNoRequest(): void
    {
        $client = new MockHttpClient([]);
        $renderer = new KrokiRenderer($client);

        self::assertSame('kroki:https://kroki.io', $renderer->getVersion());
        self::assertSame(0, $client->getRequestsCount());
    }

    /**
     * @return iterable<string, array{string, float|null}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'empty URL' => ['', 60.0];
        yield 'relative URL' => ['kroki.io', 60.0];
        yield 'unsupported scheme' => ['ftp://kroki.io', 60.0];
        yield 'missing host' => ['https://', 60.0];
        yield 'zero timeout' => ['https://kroki.io', 0.0];
        yield 'negative timeout' => ['https://kroki.io', -1.0];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsAreRejected(string $baseUrl, ?float $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KrokiRenderer(new MockHttpClient([]), $baseUrl, $timeout);
    }

    /**
     * @return array<string, string>
     */
    private static function headers(MockResponse $response): array
    {
        $headers = [];
        $normalized = $response->getRequestOptions()['normalized_headers'] ?? [];
        self::assertIsArray($normalized);

        foreach ($normalized as $name => $lines) {
            self::assertIsArray($lines);
            self::assertIsString($lines[0]);
            $headers[(string) $name] = substr($lines[0], \strlen((string) $name) + 2);
        }

        return $headers;
    }
}
