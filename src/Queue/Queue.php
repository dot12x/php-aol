<?php

declare(strict_types=1);

namespace Aol\Queue;

use Amp\DeferredFuture;

/**
 * FIFO async work queue.
 *
 * Producer pushes items; one or more consumers pop them. push blocks
 * (suspends the calling fiber) when capacity > 0 and the queue is
 * full. pop blocks when empty and the queue is still open; returns
 * null when the queue has been closed AND drained.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final class Queue implements \IteratorAggregate
{
    /** @var list<T> */
    private array $items = [];

    private bool $closed = false;

    /** @var list<DeferredFuture<T|null>> */
    private array $waitingPoppers = [];

    /** @var list<array{value: T, completion: DeferredFuture<null>}> */
    private array $waitingPushers = [];

    public function __construct(public readonly int $capacity = 0)
    {
        if ($capacity < 0) {
            throw new \InvalidArgumentException('Queue capacity must be ≥ 0 (0 = unbounded).');
        }
    }

    /**
     * @param T $item
     */
    public function push(mixed $item): void
    {
        if ($this->closed) {
            throw new \LogicException('Cannot push to a closed Queue.');
        }

        if (\count($this->waitingPoppers) > 0) {
            $popper = \array_shift($this->waitingPoppers);
            $popper->complete($item);
            return;
        }

        if ($this->capacity > 0 && \count($this->items) >= $this->capacity) {
            /** @var DeferredFuture<null> $completion */
            $completion = new DeferredFuture();
            $this->waitingPushers[] = ['value' => $item, 'completion' => $completion];
            $completion->getFuture()->await();
            return;
        }

        $this->items[] = $item;
    }

    /**
     * @return T|null  null if the queue has been closed and drained.
     */
    public function pop(): mixed
    {
        if (\count($this->items) > 0) {
            $item = \array_shift($this->items);
            if (\count($this->waitingPushers) > 0) {
                $next = \array_shift($this->waitingPushers);
                $this->items[] = $next['value'];
                $next['completion']->complete(null);
            }
            return $item;
        }

        if ($this->closed) {
            return null;
        }

        /** @var DeferredFuture<T|null> $deferred */
        $deferred = new DeferredFuture();
        $this->waitingPoppers[] = $deferred;
        return $deferred->getFuture()->await();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        foreach ($this->waitingPoppers as $popper) {
            $popper->complete(null);
        }
        $this->waitingPoppers = [];
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function size(): int
    {
        return \count($this->items);
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): \Generator
    {
        $i = 0;
        while (($item = $this->pop()) !== null) {
            yield $i++ => $item;
        }
    }
}
