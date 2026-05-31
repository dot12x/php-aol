<?php

declare(strict_types=1);

namespace Aol\Http\Sse;

/**
 * A single Server-Sent Event as parsed from a text/event-stream body.
 *
 * Fields follow the W3C SSE spec: an event name (defaults to "message"
 * when the server omits "event:"), the data payload (multiple data:
 * lines are joined with "\n"), an optional id, and an optional retry
 * hint. Note: $retry is the only value in AOL that is expressed in
 * milliseconds rather than seconds — it is a pass-through W3C wire
 * value that the server controls, not a library-managed duration.
 */
final readonly class SseEvent
{
    public function __construct(
        public string $event = 'message',
        public string $data = '',
        public ?string $id = null,
        /** @var int|null Reconnect delay in milliseconds (W3C wire value), exactly as the server sent it. */
        public ?int $retry = null,
    ) {
    }
}
