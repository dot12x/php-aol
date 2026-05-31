<?php

declare(strict_types=1);

namespace Aol\Internal\Http;

use Aol\Http\Response;
use Aol\Http\Sse\SseStream;
use Amp\Http\Client\HttpClient;

/**
 * @internal Runtime proxy that backs declarative HTTP interfaces.
 *
 * @template T of object
 */
final class ProxyInstance
{
    /**
     * @param class-string<T> $interface
     * @param array<string, MethodSpec> $methods
     */
    public function __construct(
        public readonly string $interface,
        public readonly string $baseUrl,
        public readonly array $methods,
        public readonly HttpClient $client,
    ) {
    }

    /**
     * @param array<int, mixed> $args
     */
    public function __call(string $name, array $args): mixed
    {
        if (!isset($this->methods[$name])) {
            throw new \BadMethodCallException("{$this->interface}::{$name}() not declared.");
        }
        $spec = $this->methods[$name];
        $req = $spec->buildRequest($this->baseUrl, $args);
        $ampResp = $this->client->request($req);
        if ($spec->isSse) {
            return new SseStream($ampResp->getBody());
        }
        return $spec->decodeResponse(new Response($ampResp));
    }
}
