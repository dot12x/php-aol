<?php

declare(strict_types=1);

namespace Aol\Tests\Http;

use Aol\Http;
use Aol\Http\Attribute\BaseUrl;
use Aol\Http\Attribute\Body;
use Aol\Http\Attribute\Get;
use Aol\Http\Attribute\Path;
use Aol\Http\Attribute\Post;
use Aol\Http\Response as AolResponse;
use Amp\Http\Client\HttpClientBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[BaseUrl('http://api.test')]
interface TinyApi
{
    #[Get('/users/{id}')]
    public function user(#[Path] int $id): AolResponse;

    /**
     * @param array<string, mixed> $payload
     */
    #[Post('/users')]
    public function create(#[Body] array $payload): AolResponse;
}

final class DeclarativeInterfaceTest extends TestCase
{
    private StubInterceptor $stub;

    protected function setUp(): void
    {
        $this->stub = new StubInterceptor(
            status: 200,
            body: '{"id":42,"name":"Alice"}',
            headers: ['content-type' => 'application/json'],
        );
        Http::useClient($this->stub->buildClient());
    }

    protected function tearDown(): void
    {
        Http::useClient((new HttpClientBuilder())->build());
    }

    #[Test]
    public function fromInterfaceReturnsCallableProxy(): void
    {
        $proxy = Http::fromInterface(TinyApi::class);
        self::assertInstanceOf(\Aol\Internal\Http\ProxyInstance::class, $proxy);
    }

    #[Test]
    public function userMethodIssuesGetRequest(): void
    {
        $proxy = Http::fromInterface(TinyApi::class);
        $proxy->user(42);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('GET', $req->getMethod());
        self::assertSame('http://api.test/users/42', (string) $req->getUri());
    }

    #[Test]
    public function userMethodReturnsResponse(): void
    {
        $this->stub->body = '{"id":42,"name":"Alice"}';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(TinyApi::class);
        $result = $proxy->user(42);
        self::assertInstanceOf(AolResponse::class, $result);
        self::assertSame(200, $result->status);
        $data = $result->json();
        self::assertIsArray($data);
        self::assertSame(42, $data['id']);
        self::assertSame('Alice', $data['name']);
    }

    #[Test]
    public function createMethodIssuesPostRequest(): void
    {
        $this->stub->body = '{"id":1,"name":"Bob"}';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(TinyApi::class);
        $proxy->create(['name' => 'Bob']);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('POST', $req->getMethod());
        self::assertSame('http://api.test/users', (string) $req->getUri());
    }

    #[Test]
    public function createMethodSendsBodyAsJson(): void
    {
        $this->stub->body = '{"id":1,"name":"Bob"}';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(TinyApi::class);
        $proxy->create(['name' => 'Bob']);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        $body = \Amp\ByteStream\buffer($req->getBody()->getContent());
        self::assertSame('{"name":"Bob"}', $body);
        self::assertStringContainsStringIgnoringCase('application/json', $req->getHeader('content-type') ?? '');
    }

    #[Test]
    public function createMethodReturnsResponse(): void
    {
        $this->stub->body = '{"id":1,"name":"Bob"}';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(TinyApi::class);
        $result = $proxy->create(['name' => 'Bob']);
        self::assertInstanceOf(AolResponse::class, $result);
        $data = $result->json();
        self::assertIsArray($data);
        self::assertSame(1, $data['id']);
        self::assertSame('Bob', $data['name']);
    }

    #[Test]
    public function pathParameterIsUrlEncoded(): void
    {
        $proxy = Http::fromInterface(TinyApi::class);
        $proxy->user(99);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertStringContainsString('/users/99', (string) $req->getUri());
    }

    #[Test]
    public function arrayReturnTypeDecodesJsonBody(): void
    {
        $this->stub->body = '{"id":7,"name":"Carol"}';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(ArrayReturnApi::class);
        $result = $proxy->user(7);

        self::assertSame(7, $result['id']);
        self::assertSame('Carol', $result['name']);
    }

    #[Test]
    public function mixedReturnTypeDecodesJsonBody(): void
    {
        $this->stub->body = '[1, 2, 3]';
        Http::useClient($this->stub->buildClient());

        $proxy = Http::fromInterface(ArrayReturnApi::class);
        $result = $proxy->raw();

        self::assertSame([1, 2, 3], $result);
    }
}

#[BaseUrl('http://api.test')]
interface ArrayReturnApi
{
    /**
     * @return array<string, mixed>
     */
    #[Get('/users/{id}')]
    public function user(#[Path] int $id): array;

    #[Get('/raw')]
    public function raw(): mixed;
}
