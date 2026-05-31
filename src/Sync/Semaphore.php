<?php

declare(strict_types=1);

namespace Aol\Sync;

use Amp\Sync\LocalSemaphore;

/**
 * Async-aware counting semaphore — limit concurrency to N permits.
 * Wraps amphp/sync's LocalSemaphore.
 *
 * Use withPermit() to acquire a permit, run a body, then release.
 */
final class Semaphore
{
    private readonly LocalSemaphore $impl;

    public function __construct(int $permits)
    {
        if ($permits < 1) {
            throw new \InvalidArgumentException('Semaphore permits must be ≥ 1.');
        }
        $this->impl = new LocalSemaphore($permits);
    }

    /**
     * @template T
     * @param callable(): T $body
     * @return T
     */
    public function withPermit(callable $body): mixed
    {
        $lock = $this->impl->acquire();
        try {
            return $body();
        } finally {
            $lock->release();
        }
    }
}
