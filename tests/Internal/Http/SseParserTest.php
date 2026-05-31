<?php

declare(strict_types=1);

namespace Aol\Tests\Internal\Http;

use Aol\Http\Sse\SseEvent;
use Aol\Internal\Http\SseParser;
use PHPUnit\Framework\TestCase;

final class SseParserTest extends TestCase
{
    public function testSingleDataLine(): void
    {
        $events = $this->parseAll("data: hello\n\n");
        self::assertCount(1, $events);
        self::assertSame('message', $events[0]->event);
        self::assertSame('hello', $events[0]->data);
    }

    public function testNamedEvent(): void
    {
        $events = $this->parseAll("event: tick\ndata: 1\n\n");
        self::assertSame('tick', $events[0]->event);
        self::assertSame('1', $events[0]->data);
    }

    public function testMultipleDataLinesJoinedWithNewline(): void
    {
        $events = $this->parseAll("data: a\ndata: b\ndata: c\n\n");
        self::assertSame("a\nb\nc", $events[0]->data);
    }

    public function testIdAndRetry(): void
    {
        $events = $this->parseAll("id: 7\nretry: 2500\ndata: x\n\n");
        self::assertSame('7', $events[0]->id);
        self::assertSame(2500, $events[0]->retry);
    }

    public function testCommentLinesIgnored(): void
    {
        $events = $this->parseAll(": this is a comment\ndata: x\n\n");
        self::assertCount(1, $events);
        self::assertSame('x', $events[0]->data);
    }

    public function testBlankEventNotDispatched(): void
    {
        $events = $this->parseAll("\n\n\n");
        self::assertCount(0, $events);
    }

    public function testMultipleEvents(): void
    {
        $events = $this->parseAll("data: a\n\ndata: b\n\n");
        self::assertCount(2, $events);
        self::assertSame('a', $events[0]->data);
        self::assertSame('b', $events[1]->data);
    }

    public function testCarriageReturnsAccepted(): void
    {
        $events = $this->parseAll("data: a\r\ndata: b\r\n\r\n");
        self::assertSame("a\nb", $events[0]->data);
    }

    public function testBomStrippedAtStart(): void
    {
        $events = $this->parseAll("\xEF\xBB\xBFdata: x\n\n");
        self::assertSame('x', $events[0]->data);
    }

    public function testPartialChunksAcrossBoundaries(): void
    {
        $parser = new SseParser();
        /** @var list<SseEvent> $out */
        $out = [];
        foreach (['da', 'ta: hel', "lo\n", "\n"] as $chunk) {
            foreach ($parser->feed($chunk) as $e) {
                $out[] = $e;
            }
        }
        self::assertCount(1, $out);
        self::assertSame('hello', $out[0]->data);
    }

    public function testUnknownFieldIgnored(): void
    {
        $events = $this->parseAll("foo: bar\ndata: ok\n\n");
        self::assertSame('ok', $events[0]->data);
    }

    public function testIdMayBeEmptyString(): void
    {
        $events = $this->parseAll("id\ndata: x\n\n");
        self::assertSame('', $events[0]->id);
    }

    public function testRetryNonNumericIgnored(): void
    {
        $events = $this->parseAll("retry: lol\ndata: x\n\n");
        self::assertNull($events[0]->retry);
    }

    public function testNoSpaceAfterColonPreserved(): void
    {
        $events = $this->parseAll("data:hi\n\n");
        self::assertSame('hi', $events[0]->data);
    }

    public function testIdPersistsAcrossEvents(): void
    {
        $events = $this->parseAll("id: 5\ndata: a\n\ndata: b\n\nid: 6\ndata: c\n\n");
        self::assertCount(3, $events);
        self::assertSame('5', $events[0]->id);
        self::assertSame('5', $events[1]->id);
        self::assertSame('6', $events[2]->id);
    }

    public function testFlushDiscardsUnterminatedEvent(): void
    {
        $parser = new SseParser();
        $events = [];
        foreach ($parser->feed("data: tail\n") as $e) {
            $events[] = $e;
        }
        self::assertCount(0, $events);
        foreach ($parser->flush() as $e) {
            $events[] = $e;
        }
        self::assertCount(0, $events, 'unterminated event is discarded per spec');
    }

    /** @return list<SseEvent> */
    private function parseAll(string $stream): array
    {
        $parser = new SseParser();
        /** @var list<SseEvent> $out */
        $out = [];
        foreach ($parser->feed($stream) as $e) {
            $out[] = $e;
        }
        return $out;
    }
}
