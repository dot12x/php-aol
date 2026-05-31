<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Emitted once per retry attempt, right after a failure and before
 * the backoff delay. $attempt is 1-indexed and counts the failure
 * that just happened (so attempt=1 means the first call failed and a
 * retry is about to be scheduled).
 */
final readonly class RetryAttempted extends AolEvent
{
    public function __construct(
        public string $className,
        public string $method,
        public int $attempt,
        public float $nextDelay,
        public \Throwable $error,
        float $at,
    ) {
        parent::__construct($at);
    }
}
