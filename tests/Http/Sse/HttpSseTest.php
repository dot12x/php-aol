<?php

declare(strict_types=1);

namespace Aol\Tests\Http\Sse;

use Aol\Aol;
use Aol\Http;
use Aol\Http\Sse\SseEvent;
use Aol\Tests\Http\StubInterceptor;
use Amp\Http\Client\HttpClientBuilder;
use PHPUnit\Framework\TestCase;

final class HttpSseTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::useClient((new HttpClientBuilder())->build());
    }

    public function testHttpSseStreamsEventsFromStubbedResponse(): void
    {
        $stub = new StubInterceptor(
            status: 200,
            body: "event: tick\ndata: 1\n\ndata: 2\n\n",
            headers: ['content-type' => 'text/event-stream'],
        );
        Http::useClient($stub->buildClient());

        /** @var list<SseEvent> $events */
        $events = Aol::scope(function () {
            $out = [];
            foreach (Http::sse('https://example.test/stream') as $e) {
                $out[] = $e;
            }
            return $out;
        });

        self::assertCount(2, $events);
        self::assertSame('tick', $events[0]->event);
        self::assertSame('1', $events[0]->data);
        self::assertSame('2', $events[1]->data);
    }
}
