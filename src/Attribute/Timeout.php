<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Method-level deadline. The wrapped async call must complete within
 * $seconds, otherwise it is cancelled and AolTimeoutException is
 * raised inside the Pending.
 *
 * Composite cancellation: the scope's cancellation, the method's
 * cancellation, and any enclosing scope timeout all combine — whichever
 * fires first wins.
 *
 * Durations are int/float seconds (no strings).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Timeout
{
    public function __construct(
        public int|float $seconds,
    ) {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Timeout seconds must be positive.');
        }
    }
}
