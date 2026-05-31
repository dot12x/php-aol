<?php

declare(strict_types=1);

namespace Aol;

use Aol\Internal\ScopeStack;
use Aol\Time\RevoltClock;
use Amp\CancelledException;

/**
 * Time utilities. Durations are int or float seconds — never strings.
 *
 * Inside a scope, sleep is cancellable and routed through the scope's
 * Clock (production: RevoltClock; tests: FakeClock). Outside any scope,
 * sleep falls back to a shared RevoltClock with no cancellation.
 */
final class Time
{
    private static ?RevoltClock $fallback = null;

    public static function sleep(int|float $seconds): void
    {
        $scope = ScopeStack::current();
        if ($scope !== null) {
            $scope->clock()->sleep((float) $seconds, $scope->cancellation());
            return;
        }

        if (self::$fallback === null) {
            self::$fallback = new RevoltClock();
        }
        self::$fallback->sleep((float) $seconds);
    }

    /**
     * Run $body with a hard deadline. If it does not return within
     * $seconds, AolTimeoutException is raised and any pending work
     * inside the body is cancelled. Equivalent to
     * `Aol::scope(timeout: $seconds, body: $body)`.
     *
     * @template T
     * @param callable(): T $body
     * @return T
     */
    public static function deadline(int|float $seconds, callable $body): mixed
    {
        return Aol::scope(body: $body, timeout: (float) $seconds);
    }

    /**
     * Call $body every $seconds until the active scope closes. The
     * tick runs as a background task — it doesn't block the scope
     * from completing. Body exceptions are swallowed so a flaky tick
     * doesn't take down the loop.
     */
    public static function interval(int|float $seconds, callable $body): void
    {
        $scope = ScopeStack::mustCurrent();
        $period = (float) $seconds;

        Aol::asyncBackground(static function () use ($scope, $period, $body): void {
            $cancellation = $scope->cancellation();
            while (!$cancellation->isRequested()) {
                try {
                    $scope->clock()->sleep($period, $cancellation);
                } catch (CancelledException) {
                    return;
                }
                try {
                    $body();
                } catch (\Throwable) {
                }
            }
        });
    }
}
