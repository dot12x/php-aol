<?php

declare(strict_types=1);

namespace Aol\Time;

use Amp\Cancellation;

/**
 * @internal Internal seam that lets a fake clock drop in for tests.
 */
interface Clock
{
    public function sleep(float $seconds, ?Cancellation $cancellation = null): void;

    public function now(): float;
}
