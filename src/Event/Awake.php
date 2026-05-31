<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Emitted once per wrap, after all instances have completed their
 * #[OnAwake] hooks (or immediately if the class has none).
 */
final readonly class Awake extends AolEvent
{
    public function __construct(
        public string $className,
        public int $poolSize,
        float $at,
    ) {
        parent::__construct($at);
    }
}
