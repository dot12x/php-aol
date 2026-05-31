<?php

declare(strict_types=1);

namespace Aol\Tests\Time;

use Aol\Aol;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntervalTest extends TestCase
{
    #[Test]
    public function intervalFiresPeriodicallyAndStopsOnScopeClose(): void
    {
        $ticks = 0;

        Aol::scope(function () use (&$ticks) {
            Time::interval(0.02, function () use (&$ticks) {
                $ticks++;
            });
            Time::sleep(0.10);
        });

        self::assertGreaterThanOrEqual(3, $ticks);
        self::assertLessThanOrEqual(8, $ticks);
    }

    #[Test]
    public function intervalSwallowsBodyExceptions(): void
    {
        $ticks = 0;

        Aol::scope(function () use (&$ticks) {
            Time::interval(0.02, function () use (&$ticks) {
                $ticks++;
                throw new \RuntimeException('tick boom');
            });
            Time::sleep(0.08);
        });

        self::assertGreaterThanOrEqual(2, $ticks);
    }

    #[Test]
    public function intervalStopsAfterScopeClose(): void
    {
        $ticks = 0;

        Aol::scope(function () use (&$ticks) {
            Time::interval(0.02, function () use (&$ticks) {
                $ticks++;
            });
            Time::sleep(0.05);
        });

        $afterScope = $ticks;
        Aol::scope(fn () => Time::sleep(0.1));

        self::assertSame($afterScope, $ticks, 'Interval kept ticking after its scope closed.');
    }
}
