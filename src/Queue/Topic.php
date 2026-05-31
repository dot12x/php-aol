<?php

declare(strict_types=1);

namespace Aol\Queue;

/**
 * Pub/sub fan-out. Each subscribe() returns a new Queue that receives
 * every subsequent publish(). Closing the topic closes every
 * subscriber queue.
 *
 * Late subscribers do NOT receive items published before they
 * subscribed.
 *
 * @template T
 */
final class Topic
{
    /** @var list<Queue<T>> */
    private array $subscribers = [];

    private bool $closed = false;

    /**
     * @return Queue<T>
     */
    public function subscribe(int $capacity = 0): Queue
    {
        if ($this->closed) {
            throw new \LogicException('Cannot subscribe to a closed Topic.');
        }
        /** @var Queue<T> $q */
        $q = new Queue($capacity);
        $this->subscribers[] = $q;
        return $q;
    }

    /**
     * @param T $item
     */
    public function publish(mixed $item): void
    {
        foreach ($this->subscribers as $sub) {
            if (!$sub->isClosed()) {
                $sub->push($item);
            }
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        foreach ($this->subscribers as $sub) {
            $sub->close();
        }
    }

    public function subscriberCount(): int
    {
        return \count($this->subscribers);
    }
}
