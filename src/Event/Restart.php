<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Emitted when a #[Restart]-enabled pool successfully replaced a
 * crashed instance with a fresh one. Not emitted when the restart
 * rate limit was hit (the wrapper is marked dead instead).
 */
final readonly class Restart extends AolEvent
{
    public function __construct(
        public string $className,
        public int $instanceIndex,
        public \Throwable $cause,
        float $at,
    ) {
        parent::__construct($at);
    }
}
