<?php

declare(strict_types=1);

namespace Aol\Tests\Http\Sse;

use Aol\Aol;
use Aol\Http\Sse\SseEvent;
use Aol\Http\Sse\SseStream;
use Amp\ByteStream\ReadableBuffer;
use PHPUnit\Framework\TestCase;

final class SseStreamTest extends TestCase
{
    public function testIteratesEventsFromInMemoryBody(): void
    {
        $body = new ReadableBuffer("event: tick\ndata: 1\n\ndata: 2\n\n");

        /** @var list<SseEvent> $events */
        $events = Aol::scope(function () use ($body) {
            $stream = new SseStream($body);
            $out = [];
            foreach ($stream as $event) {
                $out[] = $event;
            }
            return $out;
        });

        self::assertCount(2, $events);
        self::assertSame('tick', $events[0]->event);
        self::assertSame('1', $events[0]->data);
        self::assertSame('message', $events[1]->event);
        self::assertSame('2', $events[1]->data);
    }

    public function testEmptyBodyYieldsNothing(): void
    {
        $body = new ReadableBuffer('');
        $events = Aol::scope(function () use ($body) {
            $out = [];
            foreach (new SseStream($body) as $e) {
                $out[] = $e;
            }
            return $out;
        });
        self::assertSame([], $events);
    }
}
