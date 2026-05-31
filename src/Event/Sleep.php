<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Emitted once per wrap when the owning scope closes, after every
 * instance has run its #[OnSleep] hooks.
 */
final readonly class Sleep extends AolEvent
{
    public function __construct(
        public string $className,
        public int $poolSize,
        float $at,
    ) {
        parent::__construct($at);
    }
}
