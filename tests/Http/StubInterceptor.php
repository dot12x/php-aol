<?php

declare(strict_types=1);

namespace Aol\Tests\Http;

use Amp\ByteStream\ReadableBuffer;
use Amp\Cancellation;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

/**
 * Test stub: intercepts every request and returns a canned Response.
 * Build a real HttpClient via self::buildClient() for use with Http::useClient().
 */
final class StubInterceptor implements ApplicationInterceptor
{
    public ?Request $lastRequest = null;

    /**
     * @param array<non-empty-string, string> $headers
     */
    public function __construct(
        public int $status = 200,
        public string $body = '',
        public array $headers = ['content-type' => 'application/json'],
    ) {}

    #[\Override]
    public function request(Request $request, Cancellation $cancellation, DelegateHttpClient $httpClient): Response
    {
        $this->lastRequest = $request;
        return new Response(
            '1.1',
            $this->status,
            null,
            $this->headers,
            new ReadableBuffer($this->body),
            $request,
        );
    }

    public function buildClient(): HttpClient
    {
        return (new HttpClientBuilder())
            ->intercept($this)
            ->skipDefaultAcceptHeader()
            ->skipDefaultUserAgent()
            ->skipAutomaticCompression()
            ->retry(0)
            ->followRedirects(0)
            ->allowDeprecatedUriUserInfo()
            ->build();
    }
}
