<?php

declare(strict_types=1);

namespace Aol\Tests\Lifecycle;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\Timeout;
use Aol\Exception\AolTimeoutException;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MethodTimeoutTest extends TestCase
{
    #[Test]
    public function methodCompletesBeforeTimeoutReturnsValue(): void
    {
        $w = Aol::wrap(TimeoutTarget::class);
        $result = Aol::scope(fn () => $w->fast());
        self::assertSame('fast', $result);
    }

    #[Test]
    public function methodExceedingItsTimeoutThrows(): void
    {
        $this->expectException(AolTimeoutException::class);
        $w = Aol::wrap(TimeoutTarget::class);
        Aol::scope(fn () => $w->slow());
    }

    #[Test]
    public function methodTimeoutDoesNotAffectOtherCalls(): void
    {
        $w = Aol::wrap(TimeoutTarget::class);

        $fastResult = Aol::scope(fn () => $w->fast());
        self::assertSame('fast', $fastResult);

        // Slow method timeouts — but next call still works
        try {
            Aol::scope(fn () => $w->slow());
            self::fail('Expected timeout');
        } catch (AolTimeoutException) {
        }

        $fastAgain = Aol::scope(fn () => $w->fast());
        self::assertSame('fast', $fastAgain);
    }

    #[Test]
    public function tighterMethodTimeoutWinsOverScopeTimeout(): void
    {
        $start = microtime(true);
        $w = Aol::wrap(TimeoutTarget::class);

        try {
            Aol::scope(
                timeout: 1.0,
                body: fn () => $w->slow(),  // method timeout 0.05s
            );
            self::fail('Expected AolTimeoutException');
        } catch (AolTimeoutException) {
            $elapsed = microtime(true) - $start;
            self::assertLessThan(0.5, $elapsed, "Took {$elapsed}s — method timeout (0.05s) should have fired first.");
        }
    }

    #[Test]
    public function tighterScopeTimeoutWinsOverMethodTimeout(): void
    {
        $start = microtime(true);
        $w = Aol::wrap(TimeoutTarget::class);

        try {
            Aol::scope(
                timeout: 0.05,
                body: fn () => $w->lazy10s(),  // method timeout 10s
            );
            self::fail('Expected AolTimeoutException');
        } catch (AolTimeoutException) {
            $elapsed = microtime(true) - $start;
            self::assertLessThan(0.5, $elapsed, "Took {$elapsed}s — scope timeout (0.05s) should have fired first.");
        }
    }

    #[Test]
    public function methodWithoutTimeoutHonorsScopeTimeout(): void
    {
        $start = microtime(true);
        $w = Aol::wrap(TimeoutTarget::class);

        try {
            Aol::scope(
                timeout: 0.05,
                body: fn () => $w->unboundedSlow(),
            );
            self::fail('Expected AolTimeoutException');
        } catch (AolTimeoutException) {
            $elapsed = microtime(true) - $start;
            self::assertLessThan(0.5, $elapsed);
        }
    }
}

class TimeoutTarget
{
    #[Async]
    #[Timeout(1.0)]
    public function fast(): string
    {
        Time::sleep(0.01);
        return 'fast';
    }

    #[Async]
    #[Timeout(0.05)]
    public function slow(): string
    {
        Time::sleep(10);
        return 'never';
    }

    #[Async]
    #[Timeout(10.0)]
    public function lazy10s(): string
    {
        Time::sleep(5);
        return 'never';
    }

    #[Async]
    public function unboundedSlow(): string
    {
        Time::sleep(10);
        return 'never';
    }
}
