<?php

declare(strict_types=1);

namespace Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\OnTick;
use Aol\Attribute\Worker;
use Aol\Time;
use PHPUnit\Framework\TestCase;

final class OnTickTest extends TestCase
{
    public function testOnTickFiresPeriodicallyWhileScopeIsAlive(): void
    {
        $klass = new #[Worker] class {
            public int $count = 0;

            #[OnTick(every: 0.05)]
            public function tick(): void
            {
                $this->count++;
            }
        };

        Aol::scope(function () use ($klass) {
            $w = Aol::wrap($klass);
            Time::sleep(0.18);
            $_ = $w;
        });

        self::assertGreaterThanOrEqual(2, $klass->count, 'tick should have fired multiple times');
        self::assertLessThanOrEqual(5, $klass->count, 'tick should not have run beyond scope close');
    }

    public function testOnTickStopsAfterScopeCloses(): void
    {
        $klass = new #[Worker] class {
            public int $count = 0;

            #[OnTick(every: 0.05)]
            public function tick(): void
            {
                $this->count++;
            }
        };

        Aol::scope(function () use ($klass) {
            $w = Aol::wrap($klass);
            Time::sleep(0.12);
            $_ = $w;
        });

        $before = $klass->count;
        \usleep(150_000);
        self::assertSame($before, $klass->count, 'tick must not fire after scope close');
    }
}
