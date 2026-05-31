<?php

declare(strict_types=1);

namespace Aol\Tests\Lifecycle;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\OnAwake;
use Aol\Attribute\OnSleep;
use Aol\Attribute\Restart;
use Aol\Attribute\Worker;
use Aol\Exception\AolException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RestartTest extends TestCase
{
    protected function setUp(): void
    {
        RestartTarget::reset();
    }

    #[Test]
    public function instanceReplacedAfterCrash(): void
    {
        $w = Aol::wrap(RestartTarget::class);
        self::assertSame(1, RestartTarget::$awakeCount);

        try {
            Aol::scope(fn () => $w->fail());
        } catch (\RuntimeException) {
        }

        // OnSleep on crashed, OnAwake on replacement.
        self::assertSame(1, RestartTarget::$sleepCount);
        self::assertSame(2, RestartTarget::$awakeCount);
    }

    #[Test]
    public function nextCallAfterRestartHitsFreshInstance(): void
    {
        $w = Aol::wrap(RestartTarget::class);

        try {
            Aol::scope(fn () => $w->fail());
        } catch (\RuntimeException) {
        }

        $result = Aol::scope(fn () => $w->identify());
        self::assertSame('instance#2', $result);
    }

    #[Test]
    public function restartRateLimitMarksWrapperDead(): void
    {
        $w = Aol::wrap(LimitedRestartTarget::class);   // max: 2, within: 60

        // First 2 crashes — restart allowed.
        for ($i = 0; $i < 2; $i++) {
            try {
                Aol::scope(fn () => $w->fail());
            } catch (\RuntimeException) {
            }
        }
        self::assertFalse($w->isDead());

        // 3rd crash — limit reached, wrapper marked dead.
        try {
            Aol::scope(fn () => $w->fail());
        } catch (\RuntimeException) {
        }
        self::assertTrue($w->isDead());

        // Next call hits dead wrapper.
        $this->expectException(AolException::class);
        $this->expectExceptionMessage('is dead');
        Aol::scope(fn () => $w->fail());
    }

    #[Test]
    public function withoutRestartAttributeNoReplacement(): void
    {
        NoRestartTarget::reset();
        $w = Aol::wrap(NoRestartTarget::class);

        try {
            Aol::scope(fn () => $w->fail());
        } catch (\RuntimeException) {
        }

        self::assertSame(1, NoRestartTarget::$awakeCount);   // no second awake
        self::assertSame(0, NoRestartTarget::$sleepCount);   // no sleep on crash
    }

    #[Test]
    public function wrapOfExistingInstanceIgnoresRestart(): void
    {
        $instance = new RestartTarget();
        $w = Aol::wrap($instance);
        // After wrap, OnAwake ran once on the supplied instance.
        $awakeAfterWrap = RestartTarget::$awakeCount;
        $sleepAfterWrap = RestartTarget::$sleepCount;

        try {
            Aol::scope(fn () => $w->fail());
        } catch (\RuntimeException) {
        }

        // No factory → restart silently skipped — counts unchanged.
        self::assertSame($awakeAfterWrap, RestartTarget::$awakeCount);
        self::assertSame($sleepAfterWrap, RestartTarget::$sleepCount);
    }
}

#[Worker(pool: 1)]
#[Restart(max: 10, within: 60)]
class RestartTarget
{
    public static int $awakeCount = 0;
    public static int $sleepCount = 0;
    public static int $nextId = 1;

    public int $id;

    public function __construct()
    {
        $this->id = self::$nextId++;
    }

    public static function reset(): void
    {
        self::$awakeCount = 0;
        self::$sleepCount = 0;
        self::$nextId = 1;
    }

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
    public function fail(): string
    {
        throw new \RuntimeException('boom');
    }

    #[Async]
    public function identify(): string
    {
        return "instance#{$this->id}";
    }
}

#[Restart(max: 2, within: 60)]
class LimitedRestartTarget
{
    #[Async]
    public function fail(): string
    {
        throw new \RuntimeException('always fails');
    }
}

class NoRestartTarget
{
    public static int $awakeCount = 0;
    public static int $sleepCount = 0;

    public static function reset(): void
    {
        self::$awakeCount = 0;
        self::$sleepCount = 0;
    }

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
    public function fail(): string
    {
        throw new \RuntimeException('boom');
    }
}
