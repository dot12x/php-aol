<?php

declare(strict_types=1);

namespace Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\OnSignal;
use Aol\Attribute\Worker;
use Aol\Time;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

final class OnSignalTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\function_exists('posix_kill') || !\defined('SIGUSR1')) {
            self::markTestSkipped('pcntl/posix needed for signal test');
        }
    }

    public function testOnSignalInvokesMethod(): void
    {
        $klass = new #[Worker] class {
            public int $hits = 0;

            #[OnSignal(SIGUSR1)]
            public function onSig(): void
            {
                $this->hits++;
            }
        };

        Aol::scope(function () use ($klass) {
            $w = Aol::wrap($klass);
            EventLoop::delay(0.05, static function (): void {
                \posix_kill(\getmypid() ?: 0, SIGUSR1);
            });
            Time::sleep(0.15);
            $_ = $w;
        });

        self::assertGreaterThanOrEqual(1, $klass->hits);
    }

    public function testSignalHandlerUnregistersOnSleep(): void
    {
        $klass = new #[Worker] class {
            public int $hits = 0;

            #[OnSignal(SIGUSR1)]
            public function onSig(): void
            {
                $this->hits++;
            }
        };

        Aol::scope(function () use ($klass) {
            $w = Aol::wrap($klass);
            Time::sleep(0.02);
            $_ = $w;
        });

        \posix_kill(\getmypid() ?: 0, SIGUSR1);
        \usleep(50_000);
        self::assertSame(0, $klass->hits, 'no handler should remain after scope close');
    }
}
