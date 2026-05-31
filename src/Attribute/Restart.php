<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Class-level: when a #[Worker] instance crashes, replace it with a
 * fresh instance (one_for_one strategy — only the crashed instance,
 * not the whole pool). The current async call still fails with its
 * original exception; restart only affects future calls.
 *
 * Rate limit: at most $max restarts within $within seconds (rolling
 * window). Crossing the limit raises AolRestartLimitException, which
 * surfaces as the original async call's failure cause and halts further
 * restarts.
 *
 * Restart requires a factory: works for wraps created from a
 * class-string or a closure, NOT for wraps of an existing instance.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Restart
{
    public function __construct(
        public int $max = 5,
        public int|float $within = 60,
    ) {
        if ($max < 1) {
            throw new \InvalidArgumentException('Restart max must be ≥ 1.');
        }
        if ($within <= 0) {
            throw new \InvalidArgumentException('Restart within must be > 0.');
        }
    }
}
