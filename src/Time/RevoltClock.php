<?php

declare(strict_types=1);

namespace Aol\Time;

use Amp\Cancellation;
use function Amp\delay;
use function Amp\now;

/**
 * @internal Production clock — sleeps via Revolt event loop.
 */
final class RevoltClock implements Clock
{
    public function sleep(float $seconds, ?Cancellation $cancellation = null): void
    {
        delay($seconds, cancellation: $cancellation);
    }

    public function now(): float
    {
        return now();
    }
}
