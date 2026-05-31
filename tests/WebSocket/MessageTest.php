<?php

declare(strict_types=1);

namespace Aol\Tests\WebSocket;

use Aol\WebSocket\Message;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testTextDefaults(): void
    {
        $m = new Message('hi');
        self::assertSame('hi', $m->payload);
        self::assertFalse($m->binary);
    }

    public function testBinary(): void
    {
        $m = new Message("\x00\x01", binary: true);
        self::assertTrue($m->binary);
        self::assertSame("\x00\x01", $m->payload);
    }
}
