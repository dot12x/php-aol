<?php

declare(strict_types=1);

namespace Aol\Http\Sse;

use Aol\Internal\Http\SseParser;
use Amp\ByteStream\ReadableStream;

/**
 * Iterable stream of SseEvent parsed from a text/event-stream body.
 *
 * The body is read lazily — iteration drives reads, and the underlying
 * stream is closed when iteration ends (either naturally or because
 * the caller breaks out of the foreach). Within an Aol::scope() the
 * scope's cancellation token aborts pending reads on scope close.
 *
 * @implements \IteratorAggregate<int, SseEvent>
 */
final readonly class SseStream implements \IteratorAggregate
{
    public function __construct(private ReadableStream $body)
    {
    }

    /**
     * @return \Generator<int, SseEvent, void, void>
     */
    public function getIterator(): \Generator
    {
        $parser = new SseParser();
        $i = 0;
        try {
            while (($chunk = $this->body->read()) !== null) {
                foreach ($parser->feed($chunk) as $event) {
                    yield $i++ => $event;
                }
            }
            foreach ($parser->flush() as $event) {
                yield $i++ => $event;
            }
        } finally {
            $this->body->close();
        }
    }
}
