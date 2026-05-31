<?php

declare(strict_types=1);

namespace Aol\Tests\Http\Sse;

use Aol\Aol;
use Aol\Http;
use Aol\Http\Attribute\BaseUrl;
use Aol\Http\Attribute\Get;
use Aol\Http\Attribute\SseStream;
use Aol\Http\Sse\SseEvent;
use Aol\Tests\Http\StubInterceptor;
use Amp\Http\Client\HttpClientBuilder;
use PHPUnit\Framework\TestCase;

#[BaseUrl('https://example.test')]
interface TickFeed
{
    /** @return iterable<int, SseEvent> */
    #[Get('/ticks')]
    #[SseStream]
    public function ticks(): iterable;
}

final class DeclarativeSseTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::useClient((new HttpClientBuilder())->build());
    }

    public function testInterfaceMethodReturnsSseStream(): void
    {
        $stub = new StubInterceptor(
            status: 200,
            body: "data: a\n\ndata: b\n\n",
            headers: ['content-type' => 'text/event-stream'],
        );
        Http::useClient($stub->buildClient());

        $datas = Aol::scope(function () {
            $feed = Http::fromInterface(TickFeed::class);
            $out = [];
            foreach ($feed->ticks() as $e) {
                $out[] = $e->data;
            }
            return $out;
        });

        self::assertSame(['a', 'b'], $datas);
    }
}
