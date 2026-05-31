<?php

declare(strict_types=1);

namespace Aol\Tests\Time;

use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeSleepTest extends TestCase
{
    #[Test]
    public function sleepZeroReturnsImmediately(): void
    {
        $start = microtime(true);
        Time::sleep(0);
        $elapsed = microtime(true) - $start;
        self::assertLessThan(0.05, $elapsed);
    }

    #[Test]
    public function sleepBlocksAtLeastTheRequestedDuration(): void
    {
        $start = microtime(true);
        Time::sleep(0.05);
        $elapsed = microtime(true) - $start;
        self::assertGreaterThanOrEqual(0.04, $elapsed);
    }

    #[Test]
    public function sleepWorksOutsideAnyScope(): void
    {
        // No scope active here — must not throw, just complete.
        Time::sleep(0.01);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sleepAcceptsIntAndFloat(): void
    {
        Time::sleep(0);
        Time::sleep(0.0);
        $this->expectNotToPerformAssertions();
    }
}
