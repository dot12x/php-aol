<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SiblingCrashTest extends TestCase
{
    #[Test]
    public function siblingCrashCancelsLongRunningPending(): void
    {
        $reached = false;
        $start = microtime(true);

        try {
            Aol::scope(function () use (&$reached) {
                $a = Aol::async(function () use (&$reached) {
                    Time::sleep(10);
                    $reached = true;     // should never run
                    return 'never';
                });
                $b = Aol::async(fn () => throw new \RuntimeException('boom'));
                return [$a, $b];
            });
            self::fail('Expected RuntimeException to bubble out.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        $elapsed = microtime(true) - $start;
        self::assertLessThan(0.5, $elapsed, "Scope took {$elapsed}s — sibling cancellation didn't fire.");
        self::assertFalse($reached, 'Long-running sibling was not actually cancelled.');
    }

    #[Test]
    public function firstErrorSurvivesOverSubsequentErrors(): void
    {
        try {
            Aol::scope(function () {
                $first = Aol::async(fn () => throw new \RuntimeException('first'));
                $second = Aol::async(function () {
                    Time::sleep(0.05);
                    throw new \LogicException('second');
                });
                return [$first, $second];
            });
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('first', $e->getMessage());
        }
    }

    #[Test]
    public function discardedPendingThatCrashesStillBubblesUp(): void
    {
        // Fire-and-forget Pending that throws — scope must still surface it.
        try {
            Aol::scope(function () {
                Aol::async(fn () => throw new \RuntimeException('discarded crash'));
                Time::sleep(0.05);   // let the crash happen
            });
            self::fail('Expected discarded Pending crash to bubble.');
        } catch (\RuntimeException $e) {
            self::assertSame('discarded crash', $e->getMessage());
        }
    }
}
