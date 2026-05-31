<?php

declare(strict_types=1);

namespace Aol\Sync;

use Amp\DeferredFuture;

/**
 * Go-style wait group: track a count of in-flight tasks, then wait
 * for them all to signal done.
 *
 * Typical use:
 * <code>
 * $wg = new WaitGroup;
 * foreach ($jobs as $j) {
 *     $wg->add();
 *     Aol::async(function () use ($j, $wg) {
 *         try { process($j); } finally { $wg->done(); }
 *     });
 * }
 * $wg->wait();
 * </code>
 */
final class WaitGroup
{
    private int $count = 0;

    /** @var DeferredFuture<null>|null */
    private ?DeferredFuture $completion = null;

    public function add(int $delta = 1): void
    {
        if ($delta < 1) {
            throw new \InvalidArgumentException('WaitGroup add delta must be ≥ 1.');
        }
        $this->count += $delta;
    }

    public function done(): void
    {
        if ($this->count <= 0) {
            throw new \LogicException('WaitGroup done() called more times than add().');
        }
        $this->count--;
        if ($this->count === 0 && $this->completion !== null && !$this->completion->isComplete()) {
            $this->completion->complete();
        }
    }

    public function wait(): void
    {
        if ($this->count === 0) {
            return;
        }
        if ($this->completion === null) {
            $this->completion = new DeferredFuture();
        }
        $this->completion->getFuture()->await();
    }

    public function count(): int
    {
        return $this->count;
    }
}
