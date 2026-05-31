<?php

declare(strict_types=1);

namespace Aol\Tests\Http;

use Aol\Http\Response;
use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\Request as AmpRequest;
use Amp\Http\Client\Response as AmpResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /**
     * @param array<non-empty-string, string|list<string>> $headers
     */
    private function makeAmpResponse(
        int $status = 200,
        string $body = '',
        string $reason = 'OK',
        array $headers = ['content-type' => 'application/json'],
    ): AmpResponse {
        $req = new AmpRequest('http://example.test/', 'GET');
        return new AmpResponse(
            '1.1',
            $status,
            $reason,
            $headers,
            new ReadableBuffer($body),
            $req,
        );
    }

    #[Test]
    public function statusReturnsInnerStatus(): void
    {
        $resp = new Response($this->makeAmpResponse(status: 201));
        self::assertSame(201, $resp->status);
    }

    #[Test]
    public function bodyReturnsBufferedString(): void
    {
        $resp = new Response($this->makeAmpResponse(body: 'hello world'));
        self::assertSame('hello world', $resp->body);
    }

    #[Test]
    public function okIsTrueFor2xx(): void
    {
        self::assertTrue((new Response($this->makeAmpResponse(status: 200)))->ok);
        self::assertTrue((new Response($this->makeAmpResponse(status: 201, reason: 'Created')))->ok);
        self::assertTrue((new Response($this->makeAmpResponse(status: 204, reason: 'No Content', body: '')))->ok);
        self::assertTrue((new Response($this->makeAmpResponse(status: 299, reason: 'Custom')))->ok);
    }

    #[Test]
    public function okIsFalseFor4xx(): void
    {
        self::assertFalse((new Response($this->makeAmpResponse(status: 404, reason: 'Not Found')))->ok);
    }

    #[Test]
    public function okIsFalseFor5xx(): void
    {
        self::assertFalse((new Response($this->makeAmpResponse(status: 500, reason: 'Internal Server Error')))->ok);
    }

    #[Test]
    public function contentTypeReturnsHeader(): void
    {
        $resp = new Response($this->makeAmpResponse(headers: ['content-type' => 'text/html; charset=utf-8']));
        self::assertSame('text/html; charset=utf-8', $resp->contentType);
    }

    #[Test]
    public function contentTypeReturnsEmptyStringWhenMissing(): void
    {
        $resp = new Response($this->makeAmpResponse(headers: []));
        self::assertSame('', $resp->contentType);
    }

    #[Test]
    public function headerIsCaseInsensitive(): void
    {
        $resp = new Response($this->makeAmpResponse(headers: ['x-foo' => 'bar']));
        self::assertSame('bar', $resp->header('X-Foo'));
        self::assertSame('bar', $resp->header('x-foo'));
        self::assertSame('bar', $resp->header('X-FOO'));
    }

    #[Test]
    public function headerReturnsNullWhenMissing(): void
    {
        $resp = new Response($this->makeAmpResponse(headers: []));
        self::assertNull($resp->header('x-missing'));
    }

    #[Test]
    public function jsonDecodesBodyToArray(): void
    {
        $resp = new Response($this->makeAmpResponse(body: '{"id":1,"name":"Alice"}'));
        $data = $resp->json();
        self::assertIsArray($data);
        self::assertSame(1, $data['id']);
        self::assertSame('Alice', $data['name']);
    }

    #[Test]
    public function jsonThrowsOnInvalidJson(): void
    {
        $resp = new Response($this->makeAmpResponse(body: 'not json'));
        $this->expectException(\JsonException::class);
        $resp->json();
    }

    #[Test]
    public function asDecodesToTypedObject(): void
    {
        $resp = new Response($this->makeAmpResponse(body: '{"id":7,"name":"Bob"}'));
        $dto = $resp->as(SampleDto::class);
        self::assertInstanceOf(SampleDto::class, $dto);
        self::assertSame(7, $dto->id);
        self::assertSame('Bob', $dto->name);
    }

    #[Test]
    public function asThrowsOnNonObjectBody(): void
    {
        $resp = new Response($this->makeAmpResponse(body: '"just a string"'));
        $this->expectException(\RuntimeException::class);
        $resp->as(SampleDto::class);
    }
}

/**
 * Tiny DTO used only in ResponseTest.
 */
final class SampleDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $rawId = $data['id'] ?? 0;
        $rawName = $data['name'] ?? '';
        $id = \is_int($rawId) ? $rawId : 0;
        $name = \is_string($rawName) ? $rawName : '';
        return new self(id: $id, name: $name);
    }
}
