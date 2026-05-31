<?php

declare(strict_types=1);

namespace Aol\Tests\Http\Sse;

use Aol\Http\Sse\SseEvent;
use PHPUnit\Framework\TestCase;

final class SseEventTest extends TestCase
{
    public function testDefaultsToMessageEventEmptyData(): void
    {
        $e = new SseEvent();
        self::assertSame('message', $e->event);
        self::assertSame('', $e->data);
        self::assertNull($e->id);
        self::assertNull($e->retry);
    }

    public function testHoldsAllFields(): void
    {
        $e = new SseEvent(event: 'tick', data: 'hi', id: '42', retry: 1500);
        self::assertSame('tick', $e->event);
        self::assertSame('hi', $e->data);
        self::assertSame('42', $e->id);
        self::assertSame(1500, $e->retry);
    }
}
