<?php

declare(strict_types=1);

namespace Aol\Tests\Test;

use Aol\Test\FakeClock;
use Aol\Time;
use function Aol\Test\runScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeClockTest extends TestCase
{
    #[Test]
    public function nowStartsAtZero(): void
    {
        $clock = new FakeClock();
        self::assertSame(0.0, $clock->now());
    }

    #[Test]
    public function advanceMovesVirtualTimeForward(): void
    {
        $clock = new FakeClock();
        $clock->advance(10);
        self::assertSame(10.0, $clock->now());
        $clock->advance(5.5);
        self::assertSame(15.5, $clock->now());
    }

    #[Test]
    public function sleepInsideScopeAdvancesVirtualClock(): void
    {
        $clock = new FakeClock();

        $future = runScope($clock, function (): string {
            Time::sleep(5);
            return 'done';
        });

        $result = $future->await();
        self::assertSame('done', $result);
        self::assertSame(5.0, $clock->now());
    }

    #[Test]
    public function sleepZeroDoesNotAdvanceClock(): void
    {
        $clock = new FakeClock();
        $future = runScope($clock, function (): string {
            Time::sleep(0);
            return 'ok';
        });
        self::assertSame('ok', $future->await());
        self::assertSame(0.0, $clock->now());
    }

    #[Test]
    public function advanceBackwardsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $clock = new FakeClock();
        $clock->advance(-1);
    }

    #[Test]
    public function multipleSleepsAccumulateVirtualTime(): void
    {
        $clock = new FakeClock();
        $future = runScope($clock, function (): void {
            Time::sleep(2);
            Time::sleep(3);
        });
        $future->await();
        self::assertSame(5.0, $clock->now());
    }
}
