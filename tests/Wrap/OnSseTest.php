<?php

declare(strict_types=1);

namespace Aol\Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Http;
use Aol\Http\Attribute\OnSse;
use Aol\Http\Sse\SseEvent;
use Aol\Tests\Http\StubInterceptor;
use Aol\Time;
use Amp\Http\Client\HttpClientBuilder;
use PHPUnit\Framework\TestCase;

final class OnSseTest extends TestCase
{
    protected function tearDown(): void
    {
        Http::useClient((new HttpClientBuilder())->build());
    }

    public function testHandlerFiresPerEvent(): void
    {
        $stub = new StubInterceptor(
            status: 200,
            body: "data: a\n\ndata: b\n\ndata: c\n\n",
            headers: ['content-type' => 'text/event-stream'],
        );
        Http::useClient($stub->buildClient());

        $listener = new #[Worker] class {
            /** @var list<string> */
            public array $received = [];

            #[OnSse('https://example.test/events')]
            public function rx(SseEvent $e): void
            {
                $this->received[] = $e->data;
            }
        };

        Aol::scope(function () use ($listener) {
            $w = Aol::wrap($listener);
            Time::sleep(0.1);
            $_ = $w;
        });

        self::assertSame(['a', 'b', 'c'], $listener->received);
    }
}
