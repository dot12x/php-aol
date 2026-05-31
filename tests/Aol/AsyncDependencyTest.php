<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsyncDependencyTest extends TestCase
{
    #[Test]
    public function sumExample(): void
    {
        $result = Aol::scope(function () {
            $a = Aol::async(fn () => 1);
            $b = Aol::async(fn () => 2);
            return Aol::async(fn (int $x, int $y) => $x + $y, $a, $b);
        });
        self::assertSame(3, $result);
    }

    #[Test]
    public function mixedPendingAndLiteralPreservePositionalOrder(): void
    {
        $result = Aol::scope(function () {
            $pending = Aol::async(fn () => 'A');
            return Aol::async(
                fn (string $x, string $y, string $z) => "{$x}-{$y}-{$z}",
                $pending,
                'B',
                'C',
            );
        });
        self::assertSame('A-B-C', $result);
    }

    #[Test]
    public function chainedDependenciesResolveInOrder(): void
    {
        $result = Aol::scope(function () {
            $a = Aol::async(fn () => 1);
            $b = Aol::async(fn (int $x) => $x + 10, $a);
            return Aol::async(fn (int $x) => $x * 2, $b);
        });
        self::assertSame(22, $result);
    }

    #[Test]
    public function independentPendingsRunInParallel(): void
    {
        $start = microtime(true);
        $result = Aol::scope(function () {
            $a = Aol::async(function () {
                \Aol\Time::sleep(0.05);
                return 1;
            });
            $b = Aol::async(function () {
                \Aol\Time::sleep(0.05);
                return 2;
            });
            return Aol::async(fn (int $x, int $y) => $x + $y, $a, $b);
        });
        $elapsed = microtime(true) - $start;

        self::assertSame(3, $result);
        // Two 50ms sleeps in parallel should take ~50ms, not ~100ms.
        self::assertLessThan(0.09, $elapsed, "Parallel sleeps took {$elapsed}s — auto-graph isn't running them in parallel.");
    }
}
