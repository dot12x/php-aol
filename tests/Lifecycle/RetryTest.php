<?php

declare(strict_types=1);

namespace Aol\Tests\Lifecycle;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\Retry;
use Aol\Attribute\Timeout;
use Aol\Exception\AolTimeoutException;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    protected function setUp(): void
    {
        RetryTarget::$callCount = 0;
        RetryTarget::$failuresLeft = 0;
    }

    #[Test]
    public function retryEventuallySucceeds(): void
    {
        RetryTarget::$failuresLeft = 2;   // fail 2, succeed on 3rd
        $w = Aol::wrap(RetryTarget::class);

        $result = Aol::scope(fn () => $w->flaky());

        self::assertSame('ok', $result);
        self::assertSame(3, RetryTarget::$callCount);
    }

    #[Test]
    public function retryExhaustedThrowsLastError(): void
    {
        RetryTarget::$failuresLeft = 10;   // always fail
        $w = Aol::wrap(RetryTarget::class);

        try {
            Aol::scope(fn () => $w->flaky());   // times:3 → 4 total
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            self::assertSame('flaky failure', $e->getMessage());
        }

        self::assertSame(4, RetryTarget::$callCount, 'Should have made exactly 4 attempts (1 + retries:3).');
    }

    #[Test]
    public function timesZeroMeansSingleAttemptNoRetry(): void
    {
        RetryTarget::$failuresLeft = 1;
        $w = Aol::wrap(RetryTarget::class);

        try {
            Aol::scope(fn () => $w->onceOnly());
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException) {
        }

        self::assertSame(1, RetryTarget::$callCount);
    }

    #[Test]
    public function exceptionsNotInOnListAreNotRetried(): void
    {
        RetryTarget::$failuresLeft = 10;
        $w = Aol::wrap(RetryTarget::class);

        try {
            Aol::scope(fn () => $w->onlyRetryRuntimes());
            self::fail('Expected LogicException');
        } catch (\LogicException) {
        }

        self::assertSame(1, RetryTarget::$callCount, 'LogicException not in on:[RuntimeException::class] → no retry.');
    }

    #[Test]
    public function exponentialBackoffDelaysGrow(): void
    {
        $retry = new Retry(times: 3, backoff: 'exponential', delay: 0.1);
        self::assertEqualsWithDelta(0.1, $retry->delayFor(1), 0.001);  // 0.1 * 2^0
        self::assertEqualsWithDelta(0.2, $retry->delayFor(2), 0.001);  // 0.1 * 2^1
        self::assertEqualsWithDelta(0.4, $retry->delayFor(3), 0.001);  // 0.1 * 2^2
    }

    #[Test]
    public function linearBackoffDelaysGrow(): void
    {
        $retry = new Retry(times: 3, backoff: 'linear', delay: 0.1);
        self::assertEqualsWithDelta(0.1, $retry->delayFor(1), 0.001);
        self::assertEqualsWithDelta(0.2, $retry->delayFor(2), 0.001);
        self::assertEqualsWithDelta(0.3, $retry->delayFor(3), 0.001);
    }

    #[Test]
    public function maxDelayCapsBackoff(): void
    {
        $retry = new Retry(times: 5, backoff: 'exponential', delay: 1.0, maxDelay: 3.0);
        self::assertSame(1.0, $retry->delayFor(1));
        self::assertSame(2.0, $retry->delayFor(2));
        self::assertSame(3.0, $retry->delayFor(3));   // 4.0 capped
        self::assertSame(3.0, $retry->delayFor(4));   // 8.0 capped
    }

    #[Test]
    public function retryRespectsScopeTimeout(): void
    {
        RetryTarget::$failuresLeft = 100;
        $w = Aol::wrap(RetryTarget::class);

        $start = microtime(true);
        try {
            Aol::scope(
                timeout: 0.05,
                body: fn () => $w->slowRetries(),
            );
            self::fail('Expected AolTimeoutException');
        } catch (AolTimeoutException) {
            $elapsed = microtime(true) - $start;
            self::assertLessThan(0.5, $elapsed, "Took {$elapsed}s — scope timeout should have bailed retry loop.");
        }
    }
}

class RetryTarget
{
    public static int $callCount = 0;
    public static int $failuresLeft = 0;

    #[Async]
    #[Retry(times: 3, on: [\RuntimeException::class])]
    public function flaky(): string
    {
        self::$callCount++;
        if (self::$failuresLeft > 0) {
            self::$failuresLeft--;
            throw new \RuntimeException('flaky failure');
        }
        return 'ok';
    }

    #[Async]
    #[Retry(times: 0, on: [\RuntimeException::class])]
    public function onceOnly(): string
    {
        self::$callCount++;
        throw new \RuntimeException('once only');
    }

    #[Async]
    #[Retry(times: 5, on: [\RuntimeException::class])]
    public function onlyRetryRuntimes(): string
    {
        self::$callCount++;
        throw new \LogicException('not in on-list');
    }

    #[Async]
    #[Retry(times: 100, delay: 0.1, backoff: 'fixed')]
    public function slowRetries(): string
    {
        self::$callCount++;
        Time::sleep(0.01);
        throw new \RuntimeException('always fails');
    }
}
