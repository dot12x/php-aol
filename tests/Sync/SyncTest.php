<?php

declare(strict_types=1);

namespace Aol\Tests\Sync;

use Aol\Aol;
use Aol\Sync\Barrier;
use Aol\Sync\Mutex;
use Aol\Sync\Semaphore;
use Aol\Sync\WaitGroup;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncTest extends TestCase
{
    #[Test]
    public function mutexSerializesAccess(): void
    {
        $mu = new Mutex();
        $counter = 0;

        Aol::scope(function () use ($mu, &$counter) {
            $tasks = [];
            for ($i = 0; $i < 10; $i++) {
                $tasks[] = Aol::async(function () use ($mu, &$counter) {
                    return $mu->withLock(function () use (&$counter) {
                        $current = $counter;
                        Time::sleep(0.001);
                        $counter = $current + 1;
                        return $counter;
                    });
                });
            }
            return $tasks;
        });

        self::assertSame(10, $counter, 'Mutex must serialize the read-modify-write — without it the counter races.');
    }

    #[Test]
    public function mutexReleasesEvenOnException(): void
    {
        $mu = new Mutex();
        try {
            Aol::scope(fn () => $mu->withLock(fn () => throw new \RuntimeException('oops')));
        } catch (\RuntimeException) {
        }

        // Next acquire must work.
        $ok = Aol::scope(fn () => $mu->withLock(fn () => 'ok'));
        self::assertSame('ok', $ok);
    }

    #[Test]
    public function semaphoreLimitsConcurrency(): void
    {
        $sem = new Semaphore(permits: 2);
        $maxInFlight = 0;
        $inFlight = 0;

        Aol::scope(function () use ($sem, &$inFlight, &$maxInFlight) {
            $tasks = [];
            for ($i = 0; $i < 6; $i++) {
                $tasks[] = Aol::async(function () use ($sem, &$inFlight, &$maxInFlight) {
                    $sem->withPermit(function () use (&$inFlight, &$maxInFlight) {
                        $inFlight++;
                        $maxInFlight = \max($maxInFlight, $inFlight);
                        Time::sleep(0.02);
                        $inFlight--;
                    });
                });
            }
            return $tasks;
        });

        self::assertLessThanOrEqual(2, $maxInFlight, "Semaphore allowed {$maxInFlight} in-flight, expected ≤ 2.");
    }

    #[Test]
    public function semaphoreRejectsZeroPermits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Semaphore(0);
    }

    #[Test]
    public function barrierReleasesAllAtOnce(): void
    {
        $bar = new Barrier(parties: 3);
        $events = [];

        Aol::scope(function () use ($bar, &$events) {
            $tasks = [];
            foreach (['A', 'B', 'C'] as $label) {
                $tasks[] = Aol::async(function () use ($bar, $label, &$events) {
                    $events[] = "{$label}:before";
                    $bar->wait();
                    $events[] = "{$label}:after";
                });
            }
            return $tasks;
        });

        // All three "before" entries appear before any "after"
        $beforeCount = 0;
        foreach ($events as $e) {
            if (\str_ends_with($e, ':before')) {
                $beforeCount++;
            } elseif (\str_ends_with($e, ':after') && $beforeCount < 3) {
                self::fail("Saw 'after' before all parties reached the barrier: " . \implode(', ', $events));
            }
        }
        self::assertSame(3, $beforeCount);
    }

    #[Test]
    public function waitGroupTracksTasks(): void
    {
        $wg = new WaitGroup();
        $done = [];

        Aol::scope(function () use ($wg, &$done) {
            for ($i = 0; $i < 4; $i++) {
                $wg->add();
                $job = $i;
                Aol::async(function () use ($wg, $job, &$done) {
                    Time::sleep(0.01 * ($job + 1));
                    $done[] = $job;
                    $wg->done();
                });
            }
            return Aol::async(function () use ($wg) {
                $wg->wait();
                return 'all done';
            });
        });

        self::assertCount(4, $done);
        self::assertSame(0, $wg->count());
    }

    #[Test]
    public function waitGroupDoneWithoutAddThrows(): void
    {
        $wg = new WaitGroup();
        $this->expectException(\LogicException::class);
        $wg->done();
    }
}
