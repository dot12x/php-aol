<?php

declare(strict_types=1);

namespace Aol\Internal\Http;

use Aol\Http\Attribute\BaseUrl;
use Aol\Http\Attribute\Body;
use Aol\Http\Attribute\Delete;
use Aol\Http\Attribute\Get;
use Aol\Http\Attribute\Header;
use Aol\Http\Attribute\Headers;
use Aol\Http\Attribute\Patch;
use Aol\Http\Attribute\Path;
use Aol\Http\Attribute\Post;
use Aol\Http\Attribute\Put;
use Aol\Http\Attribute\Query;
use Aol\Http\Attribute\SseStream as SseStreamAttr;
use Aol\Http\Response;
use Aol\Support\Arr;
use Aol\Support\Cast;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request as AmpRequest;

/**
 * @internal Builds runtime proxies for declarative HTTP interfaces.
 */
final class InterfaceProxy
{
    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return ProxyInstance<T>
     */
    public static function create(string $interface, HttpClient $client): ProxyInstance
    {
        $reflection = new \ReflectionClass($interface);
        if (!$reflection->isInterface()) {
            throw new \InvalidArgumentException("{$interface} must be an interface.");
        }

        $baseUrl = '';
        $baseAttrs = $reflection->getAttributes(BaseUrl::class);
        if (\count($baseAttrs) > 0) {
            $baseUrl = $baseAttrs[0]->newInstance()->url;
        }

        $classHeaders = [];
        $headerAttrs = $reflection->getAttributes(Headers::class);
        if (\count($headerAttrs) > 0) {
            $classHeaders = $headerAttrs[0]->newInstance()->headers;
        }

        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            $methods[$method->getName()] = self::buildMethodSpec($method, $classHeaders);
        }

        return new ProxyInstance(
            interface: $interface,
            baseUrl: $baseUrl,
            methods: $methods,
            client: $client,
        );
    }

    /**
     * @param array<string, string> $classHeaders
     */
    private static function buildMethodSpec(\ReflectionMethod $method, array $classHeaders): MethodSpec
    {
        $verb = null;
        $path = null;
        foreach ([Get::class, Post::class, Put::class, Patch::class, Delete::class] as $verbAttr) {
            $attrs = $method->getAttributes($verbAttr);
            if (\count($attrs) > 0) {
                $verb = \strtoupper(\substr(\strrchr($verbAttr, '\\') ?: '\\X', 1));
                $path = $attrs[0]->newInstance()->path;
                break;
            }
        }
        if ($verb === null || $path === null) {
            throw new \LogicException("{$method->getDeclaringClass()->getName()}::{$method->getName()}() needs an HTTP verb attribute.");
        }

        $methodHeaders = $classHeaders;
        foreach ($method->getAttributes(Header::class) as $h) {
            $hi = $h->newInstance();
            if ($hi->value !== null) {
                $methodHeaders[$hi->name] = $hi->value;
            }
        }

        $params = [];
        foreach ($method->getParameters() as $i => $param) {
            $params[$i] = self::buildParamSpec($param);
        }

        $returnType = $method->getReturnType();
        $returnClass = null;
        if ($returnType instanceof \ReflectionNamedType) {
            $name = $returnType->getName();
            if (!$returnType->isBuiltin()) {
                /** @var class-string $cls */
                $cls = $name;
                $returnClass = $cls;
            } elseif ($name === 'array' || $name === 'mixed') {
                $returnClass = $name;
            }
        }

        $isSse = \count($method->getAttributes(SseStreamAttr::class)) > 0;

        return new MethodSpec($verb, $path, $methodHeaders, \array_values($params), $returnClass, $isSse);
    }

    private static function buildParamSpec(\ReflectionParameter $param): ParamSpec
    {
        $name = $param->getName();
        foreach ($param->getAttributes(Path::class) as $a) {
            $i = $a->newInstance();
            return new ParamSpec(name: $name, kind: 'path', alias: Cast::from($i->name)->defaultValue($name)->toString());
        }
        foreach ($param->getAttributes(Query::class) as $a) {
            $i = $a->newInstance();
            return new ParamSpec(name: $name, kind: 'query', alias: Cast::from($i->name)->defaultValue($name)->toString());
        }
        foreach ($param->getAttributes(Body::class) as $_) {
            return new ParamSpec(name: $name, kind: 'body', alias: $name);
        }
        foreach ($param->getAttributes(Header::class) as $a) {
            $i = $a->newInstance();
            return new ParamSpec(name: $name, kind: 'header', alias: $i->name);
        }
        return new ParamSpec(name: $name, kind: 'query', alias: $name);
    }
}

/**
 * @internal
 */
final readonly class MethodSpec
{
    /**
     * @param array<string, string> $headers
     * @param list<ParamSpec> $params
     * @param string|null $returnClass
     */
    public function __construct(
        public string $verb,
        public string $pathTemplate,
        public array $headers,
        public array $params,
        public ?string $returnClass,
        public bool $isSse = false,
    ) {
    }

    /**
     * @param array<int, mixed> $args
     */
    public function buildRequest(string $baseUrl, array $args): AmpRequest
    {
        $url = $baseUrl . $this->pathTemplate;
        $query = [];
        $body = null;
        $extraHeaders = [];

        foreach ($this->params as $i => $spec) {
            $value = Arr::from($args)->get($i);
            switch ($spec->kind) {
                case 'path':
                    $url = \str_replace('{' . $spec->alias . '}', \rawurlencode(Cast::from($value)->toString()), $url);
                    break;
                case 'query':
                    if ($value !== null) {
                        $query[$spec->alias] = Cast::from($value)->toString();
                    }
                    break;
                case 'body':
                    $body = $value;
                    break;
                case 'header':
                    if ($value !== null) {
                        $extraHeaders[$spec->alias] = Cast::from($value)->toString();
                    }
                    break;
            }
        }

        if (\count($query) > 0) {
            $url .= (\str_contains($url, '?') ? '&' : '?') . \http_build_query($query);
        }

        if ($this->verb === '') {
            throw new \InvalidArgumentException('Http method must be non-empty');
        }
        $req = new AmpRequest($url, $this->verb);
        foreach ($this->headers as $name => $value) {
            if ($name === '') {
                continue;
            }
            $req->setHeader($name, $value);
        }
        foreach ($extraHeaders as $name => $value) {
            if ($name === '') {
                continue;
            }
            $req->setHeader($name, $value);
        }

        if ($body !== null) {
            if (\is_array($body) || \is_object($body)) {
                $req->setBody(\json_encode($body, \JSON_THROW_ON_ERROR));
                if (!$req->hasHeader('content-type')) {
                    $req->setHeader('Content-Type', 'application/json');
                }
            } elseif (\is_string($body)) {
                $req->setBody($body);
            }
        }

        if ($this->isSse) {
            $req->setHeader('Accept', 'text/event-stream');
            $req->setBodySizeLimit(\PHP_INT_MAX);
            $req->setTransferTimeout(0);
            $req->setInactivityTimeout(0);
        }

        return $req;
    }

    public function decodeResponse(Response $resp): mixed
    {
        if ($this->returnClass === null) {
            return null;
        }
        if ($this->returnClass === Response::class) {
            return $resp;
        }
        if ($this->returnClass === 'array' || $this->returnClass === 'mixed') {
            return $resp->json();
        }
        if (!\class_exists($this->returnClass)) {
            throw new \RuntimeException('Unknown return class: ' . $this->returnClass);
        }
        /** @var class-string $cls */
        $cls = $this->returnClass;
        return $resp->as($cls);
    }
}

/**
 * @internal
 */
final readonly class ParamSpec
{
    public function __construct(
        public string $name,
        public string $kind,
        public string $alias,
    ) {
    }
}
