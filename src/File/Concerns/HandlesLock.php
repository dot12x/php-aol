<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\Handle;
use Amp\File\LockType;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesLock
{
    /**
     * Acquire a file lock for the duration of $body. The lock is
     * released in a finally block whether $body returns or throws.
     *
     * Caveat: amphp/file's ParallelFile driver has issues with
     * truncate-then-write inside a single handle while holding the
     * lock. Prefer reading inside the lock and writing the new value
     * separately via the static facade (atomic: true).
     *
     * @template T
     * @param callable(Handle): T $body
     * @return T
     */
    public static function withLock(string $path, callable $body, bool $exclusive = true): mixed
    {
        $handle = self::open($path, 'r+');
        try {
            $handle->inner()->lock($exclusive ? LockType::Exclusive : LockType::Shared);
            try {
                return $body($handle);
            } finally {
                $handle->inner()->unlock();
            }
        } finally {
            $handle->close();
        }
    }
}
