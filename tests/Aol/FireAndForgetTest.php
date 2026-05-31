<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FireAndForgetTest extends TestCase
{
    #[Test]
    public function discardedPendingStillDelaysScopeClose(): void
    {
        $start = microtime(true);

        Aol::scope(function () {
            Aol::async(fn () => Time::sleep(0.05));   // discarded — scope still owns it
        });

        $elapsed = microtime(true) - $start;
        self::assertGreaterThanOrEqual(0.04, $elapsed, "Scope closed in {$elapsed}s — fire-and-forget Pending was not awaited.");
    }

    #[Test]
    public function discardedPendingCanRunSideEffects(): void
    {
        $sideEffect = null;

        Aol::scope(function () use (&$sideEffect) {
            Aol::async(function () use (&$sideEffect) {
                Time::sleep(0.02);
                $sideEffect = 'done';
            });
        });

        self::assertSame('done', $sideEffect);
    }
}
