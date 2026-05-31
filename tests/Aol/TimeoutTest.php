<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use Aol\Exception\AolTimeoutException;
use Aol\Time;
use Amp\TimeoutException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeoutTest extends TestCase
{
    #[Test]
    public function timeoutFiresWhenBodyExceedsDeadline(): void
    {
        $start = microtime(true);
        try {
            Aol::scope(timeout: 0.05, body: fn () => Time::sleep(10));
            self::fail('Expected AolTimeoutException.');
        } catch (AolTimeoutException) {
            $elapsed = microtime(true) - $start;
            self::assertLessThan(0.3, $elapsed, "Timeout took {$elapsed}s — should be ~50ms.");
        }
    }

    #[Test]
    public function timeoutDoesNotFireWhenBodyFinishesInTime(): void
    {
        $result = Aol::scope(timeout: 1.0, body: function () {
            Time::sleep(0.01);
            return 'ok';
        });
        self::assertSame('ok', $result);
    }

    #[Test]
    public function timeoutExceptionWrapsAmpTimeoutAsPrevious(): void
    {
        try {
            Aol::scope(timeout: 0.01, body: fn () => Time::sleep(10));
            self::fail('Expected AolTimeoutException.');
        } catch (AolTimeoutException $e) {
            self::assertInstanceOf(TimeoutException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function timeoutCancelsInFlightAsyncCalls(): void
    {
        $reached = false;

        try {
            Aol::scope(timeout: 0.05, body: function () use (&$reached) {
                return Aol::async(function () use (&$reached) {
                    Time::sleep(10);
                    $reached = true;   // should never run
                    return 'never';
                });
            });
            self::fail('Expected AolTimeoutException.');
        } catch (AolTimeoutException) {
            // expected
        }

        self::assertFalse($reached, 'Sleep was not actually cancelled by timeout.');
    }
}
