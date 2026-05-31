<?php

declare(strict_types=1);

namespace Aol\Http;

use Aol\Support\Arr;
use Aol\Support\Cast;
use Amp\Http\Client\Response as AmpResponse;

/**
 * HTTP response wrapper. Status, body, headers + a few helpers.
 *
 *   $resp->status   int
 *   $resp->body     string  (fully read)
 *   $resp->ok       bool    (2xx)
 *   $resp->json()   mixed
 *   $resp->as(User::class)  User|null
 */
final class Response
{
    private readonly string $bodyString;

    /** @var array<string, list<string>> */
    private readonly array $headerMap;

    public function __construct(AmpResponse $inner)
    {
        $this->bodyString = $inner->getBody()->buffer();
        /** @var array<string, list<string>> $headers */
        $headers = $inner->getHeaders();
        $this->headerMap = $headers;
        $this->status = $inner->getStatus();
    }

    public readonly int $status;

    public string $body {
        get => $this->bodyString;
    }

    public bool $ok {
        get => $this->status >= 200 && $this->status < 300;
    }

    public string $contentType {
        get => Cast::from($this->header('content-type'))->defaultValue('')->toString();
    }

    public function header(string $name): ?string
    {
        $values = Arr::from($this->headerMap)->get(\strtolower($name));
        if (!\is_array($values) || $values === []) {
            return null;
        }
        return Cast::from($values[0])->defaultValue('')->toString();
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headerMap;
    }

    public function json(bool $assoc = true): mixed
    {
        return \json_decode($this->bodyString, $assoc, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function as(string $class): object
    {
        $data = $this->json(assoc: true);
        if (!\is_array($data)) {
            throw new \RuntimeException("Response body is not a JSON object — cannot decode as {$class}.");
        }
        if (\method_exists($class, 'fromArray')) {
            /** @var T */
            return $class::fromArray($data);
        }
        /** @var T */
        return new $class(...$data);
    }
}
