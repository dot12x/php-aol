<?php

declare(strict_types=1);

namespace Aol;

use Aol\Http\Response;
use Aol\Internal\Http\InterfaceProxy;
use Aol\Internal\Http\ProxyInstance;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request as AmpRequest;

/**
 * Async HTTP facade.
 *
 *   $resp = Http::get('https://api.example.com/data');
 *   $resp = Http::post($url, json: $payload);
 *   $resp = Http::request('PATCH', $url, headers: [...], body: '...');
 *
 * For the declarative Retrofit-style interface mode, use
 * Aol::http(MyApi::class).
 */
final class Http
{
    private static ?HttpClient $client = null;

    /**
     * @param array<string, string> $headers
     */
    public static function get(string $url, array $headers = []): Response
    {
        return self::request('GET', $url, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function post(string $url, ?string $body = null, mixed $json = null, array $headers = []): Response
    {
        if ($json !== null && $body === null) {
            $body = \json_encode($json, \JSON_THROW_ON_ERROR);
            if (!isset($headers['content-type']) && !isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }
        }
        return self::request('POST', $url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function put(string $url, ?string $body = null, mixed $json = null, array $headers = []): Response
    {
        if ($json !== null && $body === null) {
            $body = \json_encode($json, \JSON_THROW_ON_ERROR);
            if (!isset($headers['content-type']) && !isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }
        }
        return self::request('PUT', $url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function patch(string $url, ?string $body = null, mixed $json = null, array $headers = []): Response
    {
        if ($json !== null && $body === null) {
            $body = \json_encode($json, \JSON_THROW_ON_ERROR);
            if (!isset($headers['content-type']) && !isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/json';
            }
        }
        return self::request('PATCH', $url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function delete(string $url, array $headers = []): Response
    {
        return self::request('DELETE', $url, $headers);
    }

    /**
     * Open a Server-Sent Events stream. Returns an iterable of SseEvent.
     *
     * Must be called inside Aol::scope() because the iterator reads from
     * a live response body; scope cancellation closes the body.
     *
     * @param array<string, string> $headers
     */
    public static function sse(string $url, array $headers = []): \Aol\Http\Sse\SseStream
    {
        $headers['Accept'] = 'text/event-stream';
        $req = new AmpRequest($url, 'GET');
        foreach ($headers as $name => $value) {
            if ($name === '') {
                continue;
            }
            $req->setHeader($name, $value);
        }
        $req->setBodySizeLimit(\PHP_INT_MAX);
        $req->setTransferTimeout(0);
        $req->setInactivityTimeout(0);

        $resp = self::client()->request($req);
        return new \Aol\Http\Sse\SseStream($resp->getBody());
    }

    /**
     * @param array<string, string> $headers
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        if ($method === '') {
            throw new \InvalidArgumentException('Http method must be non-empty');
        }
        $req = new AmpRequest($url, $method);
        foreach ($headers as $name => $value) {
            if ($name === '') {
                continue;
            }
            $req->setHeader($name, $value);
        }
        if ($body !== null) {
            $req->setBody($body);
        }
        $client = self::client();
        return new Response($client->request($req));
    }

    /**
     * Build a proxy for a declarative interface.
     *
     * @template T of object
     * @param class-string<T> $interface
     * @return ProxyInstance<T>
     */
    public static function fromInterface(string $interface): object
    {
        return InterfaceProxy::create($interface, self::client());
    }

    /**
     * Override the global client (e.g. with custom interceptors).
     */
    public static function useClient(HttpClient $client): void
    {
        self::$client = $client;
    }

    private static function client(): HttpClient
    {
        if (self::$client === null) {
            self::$client = (new HttpClientBuilder())->build();
        }
        return self::$client;
    }
}
