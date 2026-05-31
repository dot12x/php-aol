<?php

declare(strict_types=1);

namespace Aol\Internal\Http;

use Aol\Http\Sse\SseEvent;
use Aol\Support\Cast;

/**
 * @internal Byte-level W3C text/event-stream parser.
 *
 * Accepts arbitrary chunks via feed() (chunks may split lines or even
 * a single field across calls) and yields completed SseEvent instances
 * as soon as a blank line dispatches them. Unterminated events at EOF
 * are discarded, per the spec.
 */
final class SseParser
{
    private string $buffer = '';
    private bool $bomChecked = false;

    private ?string $lastId = null;

    private string $event = '';
    private string $data = '';
    private ?int $retry = null;
    private bool $hasField = false;

    /**
     * Feed a chunk of bytes. Yields zero or more parsed events.
     *
     * @return iterable<int, SseEvent>
     */
    public function feed(string $chunk): iterable
    {
        if (!$this->bomChecked) {
            $this->bomChecked = true;
            if (\str_starts_with($chunk, "\xEF\xBB\xBF")) {
                $chunk = \substr($chunk, 3);
            }
        }

        $this->buffer .= $chunk;

        while (($pos = $this->nextLineBreak($this->buffer)) !== null) {
            [$line, $skip] = $pos;
            $raw = \substr($this->buffer, 0, $line);
            $this->buffer = \substr($this->buffer, $line + $skip);

            if ($raw === '') {
                if ($this->hasField) {
                    yield $this->flushEvent();
                }
                continue;
            }
            $this->processLine($raw);
        }
    }

    /**
     * Discard any buffered, undispatched event. Yields nothing per spec
     * (unterminated events are dropped), but keeps the signature
     * iterable so callers can foreach uniformly.
     *
     * @return iterable<int, SseEvent>
     */
    public function flush(): iterable
    {
        $this->resetEvent();
        return [];
    }

    /**
     * Find the next "\n", "\r\n", or "\r". Returns [lineEnd, skip] where
     * lineEnd is the offset of the line terminator's first byte and skip
     * is how many bytes of terminator to consume. Returns null if none
     * of the three appears yet.
     *
     * @return array{0:int,1:int}|null
     */
    private function nextLineBreak(string $buffer): ?array
    {
        $len = \strlen($buffer);
        for ($i = 0; $i < $len; $i++) {
            $c = $buffer[$i];
            if ($c === "\n") {
                return [$i, 1];
            }
            if ($c === "\r") {
                if ($i + 1 < $len && $buffer[$i + 1] === "\n") {
                    return [$i, 2];
                }
                if ($i + 1 < $len) {
                    return [$i, 1];
                }
                return null;
            }
        }
        return null;
    }

    private function processLine(string $line): void
    {
        if ($line[0] === ':') {
            return;
        }

        $colon = \strpos($line, ':');
        if ($colon === false) {
            $field = $line;
            $value = '';
        } else {
            $field = \substr($line, 0, $colon);
            $value = \substr($line, $colon + 1);
            if ($value !== '' && $value[0] === ' ') {
                $value = \substr($value, 1);
            }
        }

        switch ($field) {
            case 'event':
                $this->event = $value;
                $this->hasField = true;
                return;
            case 'data':
                if ($this->data === '') {
                    $this->data = $value;
                } else {
                    $this->data .= "\n" . $value;
                }
                $this->hasField = true;
                return;
            case 'id':
                if (!\str_contains($value, "\0")) {
                    $this->lastId = $value;
                    $this->hasField = true;
                }
                return;
            case 'retry':
                if (\ctype_digit($value)) {
                    $this->retry = Cast::from($value)->toInt();
                    $this->hasField = true;
                }
                return;
        }
    }

    private function flushEvent(): SseEvent
    {
        $event = new SseEvent(
            event: $this->event === '' ? 'message' : $this->event,
            data: $this->data,
            id: $this->lastId,
            retry: $this->retry,
        );
        $this->resetEvent();
        return $event;
    }

    private function resetEvent(): void
    {
        $this->event = '';
        $this->data = '';
        $this->retry = null;
        $this->hasField = false;
    }
}
