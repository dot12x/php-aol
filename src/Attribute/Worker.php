<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Declare that a class is animated as a pool of N instances when
 * wrapped. Without this attribute, the wrapped class behaves as
 * pool: 1 (single instance).
 *
 * - $pool: number of instances created eagerly at Aol::wrap() time.
 * - $queue: max pending calls awaiting a free instance (backpressure).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Worker
{
    public function __construct(
        public int $pool = 1,
        public int $queue = 1024,
    ) {
    }
}
