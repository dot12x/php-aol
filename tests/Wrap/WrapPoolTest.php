<?php

declare(strict_types=1);

namespace Aol\Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\Worker;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WrapPoolTest extends TestCase
{
    #[Test]
    public function defaultPoolSizeIsOneWhenWorkerAttributeAbsent(): void
    {
        $w = Aol::wrap(PoolTargetWithoutWorker::class);
        self::assertCount(1, $w->inflightSnapshot());
    }

    #[Test]
    public function workerAttributeCreatesPoolEagerly(): void
    {
        $w = Aol::wrap(PoolTargetFour::class);
        self::assertCount(4, $w->inflightSnapshot());
        self::assertSame([0, 0, 0, 0], $w->inflightSnapshot());
    }

    #[Test]
    public function leastBusyDispatchSpreadsLoad(): void
    {
        $snapshots = [];

        Aol::scope(function () use (&$snapshots) {
            $w = Aol::wrap(PoolTargetFour::class);

            // Fire 4 long-running tasks — should spread across all 4 instances.
            $tasks = [];
            for ($i = 0; $i < 4; $i++) {
                $tasks[] = $w->slow(0.05);
            }
            // Capture inflight snapshot while all 4 are running.
            $snapshots[] = $w->inflightSnapshot();
            return $tasks;
        });

        // While the 4 calls were in-flight, each instance should have had
        // exactly 1 call assigned (perfect spread).
        self::assertSame([1, 1, 1, 1], $snapshots[0]);
    }

    #[Test]
    public function existingInstanceWrappedAsSingletonRegardlessOfWorker(): void
    {
        $instance = new PoolTargetFour();
        $w = Aol::wrap($instance);
        self::assertCount(1, $w->inflightSnapshot());
    }

    #[Test]
    public function factoryClosureCalledOncePerPoolSlot(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount): PoolTargetFour {
            $callCount++;
            return new PoolTargetFour();
        };
        $w = Aol::wrap($factory);
        self::assertSame(4, $callCount);
        self::assertCount(4, $w->inflightSnapshot());
    }

    #[Test]
    public function classWithConstructorArgsInstantiatedWithArgs(): void
    {
        $w = Aol::wrap(PoolTargetWithConstructor::class, multiplier: 10);
        $result = Aol::scope(fn () => $w->multiply(5));
        self::assertSame(50, $result);
    }
}

class PoolTargetWithoutWorker
{
    #[Async]
    public function noop(): int
    {
        return 1;
    }
}

#[Worker(pool: 4)]
class PoolTargetFour
{
    #[Async]
    public function slow(float $seconds): string
    {
        Time::sleep($seconds);
        return 'done';
    }
}

class PoolTargetWithConstructor
{
    public function __construct(public int $multiplier = 1)
    {
    }

    #[Async]
    public function multiply(int $x): int
    {
        return $x * $this->multiplier;
    }
}
