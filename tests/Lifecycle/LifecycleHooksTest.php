<?php

declare(strict_types=1);

namespace Aol\Tests\Lifecycle;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\OnAwake;
use Aol\Attribute\OnSleep;
use Aol\Attribute\Worker;
use Aol\Exception\AolWrapException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LifecycleHooksTest extends TestCase
{
    #[Test]
    public function onAwakeRunsOncePerInstanceImmediately(): void
    {
        TrackingTarget::$awakeCount = 0;
        Aol::wrap(TrackingTarget::class);
        self::assertSame(1, TrackingTarget::$awakeCount);
    }

    #[Test]
    public function onAwakeRunsForEveryPoolInstance(): void
    {
        PoolAwakeTarget::$awakeCount = 0;
        Aol::wrap(PoolAwakeTarget::class);
        self::assertSame(4, PoolAwakeTarget::$awakeCount);
    }

    #[Test]
    public function onAwakeFailureRaisesAolWrapException(): void
    {
        $this->expectException(AolWrapException::class);
        $this->expectExceptionMessage('OnAwake failed for');
        Aol::wrap(FailingAwakeTarget::class);
    }

    #[Test]
    public function onAwakeFailurePreservesOriginalAsPrevious(): void
    {
        try {
            Aol::wrap(FailingAwakeTarget::class);
        } catch (AolWrapException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            self::assertSame('init kaboom', $e->getPrevious()->getMessage());
        }
    }

    #[Test]
    public function onSleepRunsAtScopeCloseForWrapsCreatedInside(): void
    {
        TrackingTarget::$sleepCount = 0;

        Aol::scope(function () {
            $w = Aol::wrap(TrackingTarget::class);
            return $w->noop();
        });

        self::assertSame(1, TrackingTarget::$sleepCount);
    }

    #[Test]
    public function onSleepDoesNotRunForWrapsCreatedOutsideScope(): void
    {
        TrackingTarget::$sleepCount = 0;
        Aol::wrap(TrackingTarget::class);   // outside any scope

        Aol::scope(fn () => null);          // unrelated scope

        self::assertSame(0, TrackingTarget::$sleepCount);
    }

    #[Test]
    public function onSleepRunsEvenWhenScopeBodyThrows(): void
    {
        TrackingTarget::$sleepCount = 0;

        try {
            Aol::scope(function () {
                Aol::wrap(TrackingTarget::class);
                throw new \LogicException('body bang');
            });
        } catch (\LogicException) {
            // expected
        }

        self::assertSame(1, TrackingTarget::$sleepCount);
    }

    #[Test]
    public function multipleHookMethodsAllFire(): void
    {
        MultiHookTarget::$awakeMethods = [];
        MultiHookTarget::$sleepMethods = [];

        Aol::scope(function () {
            Aol::wrap(MultiHookTarget::class);
        });

        sort(MultiHookTarget::$awakeMethods);
        sort(MultiHookTarget::$sleepMethods);

        self::assertSame(['init', 'warmCache'], MultiHookTarget::$awakeMethods);
        self::assertSame(['cleanup', 'flushBuffers'], MultiHookTarget::$sleepMethods);
    }
}

class TrackingTarget
{
    public static int $awakeCount = 0;
    public static int $sleepCount = 0;

    #[OnAwake]
    public function init(): void
    {
        self::$awakeCount++;
    }

    #[OnSleep]
    public function cleanup(): void
    {
        self::$sleepCount++;
    }

    #[Async]
    public function noop(): int
    {
        return 0;
    }
}

#[Worker(pool: 4)]
class PoolAwakeTarget
{
    public static int $awakeCount = 0;

    #[OnAwake]
    public function init(): void
    {
        self::$awakeCount++;
    }
}

class FailingAwakeTarget
{
    #[OnAwake]
    public function init(): void
    {
        throw new \RuntimeException('init kaboom');
    }
}

class MultiHookTarget
{
    /** @var list<string> */
    public static array $awakeMethods = [];
    /** @var list<string> */
    public static array $sleepMethods = [];

    #[OnAwake]
    public function init(): void
    {
        self::$awakeMethods[] = 'init';
    }

    #[OnAwake]
    public function warmCache(): void
    {
        self::$awakeMethods[] = 'warmCache';
    }

    #[OnSleep]
    public function cleanup(): void
    {
        self::$sleepMethods[] = 'cleanup';
    }

    #[OnSleep]
    public function flushBuffers(): void
    {
        self::$sleepMethods[] = 'flushBuffers';
    }
}
