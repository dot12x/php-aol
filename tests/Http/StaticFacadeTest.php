<?php

declare(strict_types=1);

namespace Aol\Tests\Http;

use Aol\Http;
use Amp\Http\Client\HttpClientBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StaticFacadeTest extends TestCase
{
    private StubInterceptor $stub;

    protected function setUp(): void
    {
        $this->stub = new StubInterceptor(
            status: 200,
            body: '{"ok":true}',
            headers: ['content-type' => 'application/json'],
        );
        Http::useClient($this->stub->buildClient());
    }

    protected function tearDown(): void
    {
        Http::useClient((new HttpClientBuilder())->build());
    }

    #[Test]
    public function getUsesCorrectUriAndMethod(): void
    {
        Http::get('http://example.test/data');
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('http://example.test/data', (string) $req->getUri());
        self::assertSame('GET', $req->getMethod());
    }

    #[Test]
    public function postSendsJsonBodyAndHeader(): void
    {
        Http::post('http://example.test/items', json: ['a' => 1]);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('POST', $req->getMethod());
        $body = \Amp\ByteStream\buffer($req->getBody()->getContent());
        self::assertSame('{"a":1}', $body);
        self::assertStringContainsStringIgnoringCase('application/json', $req->getHeader('content-type') ?? '');
    }

    #[Test]
    public function getResponseStatusMatchesStub(): void
    {
        $this->stub->status = 404;
        $this->stub->body = '{}';
        Http::useClient($this->stub->buildClient());

        $resp = Http::get('http://example.test/missing');
        self::assertSame(404, $resp->status);
    }

    #[Test]
    public function getResponseJsonDecodesBody(): void
    {
        $this->stub->body = '{"message":"hello"}';
        Http::useClient($this->stub->buildClient());

        $data = Http::get('http://example.test/msg')->json();
        self::assertIsArray($data);
        self::assertSame('hello', $data['message']);
    }

    #[Test]
    public function errorResponseDoesNotThrow(): void
    {
        $this->stub->status = 500;
        $this->stub->body = '{"error":"boom"}';
        Http::useClient($this->stub->buildClient());

        $resp = Http::get('http://example.test/broken');
        self::assertSame(500, $resp->status);
        self::assertFalse($resp->ok);
    }

    #[Test]
    public function clientErrorResponseDoesNotThrow(): void
    {
        $this->stub->status = 403;
        $this->stub->body = '{"error":"forbidden"}';
        Http::useClient($this->stub->buildClient());

        $resp = Http::get('http://example.test/forbidden');
        self::assertSame(403, $resp->status);
        self::assertFalse($resp->ok);
    }

    #[Test]
    public function deleteUsesCorrectMethod(): void
    {
        Http::delete('http://example.test/item/1');
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('DELETE', $req->getMethod());
    }

    #[Test]
    public function putSendsJsonBody(): void
    {
        Http::put('http://example.test/item/1', json: ['x' => 2]);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('PUT', $req->getMethod());
        $body = \Amp\ByteStream\buffer($req->getBody()->getContent());
        self::assertSame('{"x":2}', $body);
    }

    #[Test]
    public function patchSendsJsonBody(): void
    {
        Http::patch('http://example.test/item/1', json: ['z' => 3]);
        $req = $this->stub->lastRequest;
        self::assertNotNull($req);
        self::assertSame('PATCH', $req->getMethod());
        $body = \Amp\ByteStream\buffer($req->getBody()->getContent());
        self::assertSame('{"z":3}', $body);
    }
}
