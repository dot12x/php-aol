<?php

declare(strict_types=1);

namespace Aol\Sync;

use Amp\Sync\Barrier as AmpBarrier;

/**
 * Synchronization point for N parties. Each party calls wait() — the
 * first N-1 callers block; when the Nth caller arrives, all are
 * released together.
 *
 * One-shot (not cyclic): once N parties have crossed, further wait()
 * calls return immediately.
 */
final class Barrier
{
    private readonly AmpBarrier $impl;

    public function __construct(int $parties)
    {
        if ($parties < 1) {
            throw new \InvalidArgumentException('Barrier parties must be ≥ 1.');
        }
        $this->impl = new AmpBarrier($parties);
    }

    public function wait(): void
    {
        $this->impl->arrive();
        $this->impl->await();
    }
}
