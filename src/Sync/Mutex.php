<?php

declare(strict_types=1);

namespace Aol\Sync;

use Amp\Sync\LocalMutex;

/**
 * Async-aware mutual exclusion. Wraps amphp/sync's LocalMutex.
 *
 * Use withLock() to run a critical section — the lock is acquired
 * before the body runs and released in a finally block whether the
 * body returns or throws.
 */
final class Mutex
{
    private readonly LocalMutex $impl;

    public function __construct()
    {
        $this->impl = new LocalMutex();
    }

    /**
     * @template T
     * @param callable(): T $body
     * @return T
     */
    public function withLock(callable $body): mixed
    {
        $lock = $this->impl->acquire();
        try {
            return $body();
        } finally {
            $lock->release();
        }
    }
}
