<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use Aol\Exception\AolTimeoutException;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Turn 1 acceptance gate — verbatim snippets from the plan's
 * "Acceptance criteria" section. If any of these fail, Turn 1 is
 * incomplete.
 */
final class MvpExampleTest extends TestCase
{
    #[Test]
    public function aAutoGraphDependency(): void
    {
        $result = Aol::scope(function () {
            $a = Aol::async(fn () => 1);
            $b = Aol::async(fn () => 2);
            return Aol::async(fn (int $x, int $y) => $x + $y, $a, $b);
        });
        self::assertSame(3, $result);
    }

    #[Test]
    public function bAsyncSleepInScope(): void
    {
        Aol::scope(fn () => Time::sleep(0.05));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function cAsyncSleepOutOfScope(): void
    {
        Time::sleep(0.02);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function dTimeout(): void
    {
        $this->expectException(AolTimeoutException::class);
        Aol::scope(timeout: 0.05, body: fn () => Time::sleep(10));
    }

    #[Test]
    public function eNestedScope(): void
    {
        $x = Aol::scope(fn () => Aol::scope(fn () => Aol::async(fn () => 42)));
        self::assertSame(42, $x);
    }

    #[Test]
    public function fSiblingCrashOneForAll(): void
    {
        try {
            Aol::scope(function () {
                $a = Aol::async(fn () => Time::sleep(10));
                $b = Aol::async(fn () => throw new \RuntimeException('boom'));
                return [$a, $b];
            });
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
    }

    #[Test]
    public function gFireAndForgetScopeStillWaits(): void
    {
        $start = microtime(true);
        Aol::scope(function () {
            Aol::async(fn () => Time::sleep(0.05));
        });
        $elapsed = microtime(true) - $start;
        self::assertGreaterThanOrEqual(0.04, $elapsed);
    }
}
