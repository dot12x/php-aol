<?php

declare(strict_types=1);

namespace Aol\Test;

use Aol\Time\Clock;
use Amp\Cancellation;

/**
 * Manually-controlled clock for deterministic async tests.
 *
 * In this MVP variant, sleep does not actually suspend — it simply
 * advances the virtual clock by the requested amount and returns.
 * advance() pushes the virtual clock forward without any sleep
 * coupling. The advance/await interleaving pattern needed for full
 * deadline-ordered semantics arrives with retry/timeout in Turn 3.
 *
 * Typical pattern:
 * <code>
 * $clock = new FakeClock;
 * $result = runScope($clock, fn () => Time::sleep(5))->await();
 * assert($clock->now() === 5.0);
 * </code>
 */
final class FakeClock implements Clock
{
    private float $virtualTime = 0.0;

    public function sleep(float $seconds, ?Cancellation $cancellation = null): void
    {
        if ($seconds <= 0) {
            return;
        }
        $this->virtualTime += $seconds;
    }

    public function now(): float
    {
        return $this->virtualTime;
    }

    /**
     * Push virtual time forward manually (without invoking any sleep).
     */
    public function advance(float $seconds): void
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Cannot advance backwards.');
        }
        $this->virtualTime += $seconds;
    }
}
