<?php

declare(strict_types=1);

namespace Aol\Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Pending;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WrapAsyncMethodTest extends TestCase
{
    #[Test]
    public function asyncMethodReturnsPendingInsideScope(): void
    {
        $result = Aol::scope(function () {
            $w = Aol::wrap(WrapTarget::class);
            $r = $w->add(2, 3);
            self::assertInstanceOf(Pending::class, $r);
            return $r;
        });
        self::assertSame(5, $result);
    }

    #[Test]
    public function syncMethodReturnsRealValueImmediately(): void
    {
        $w = Aol::wrap(WrapTarget::class);
        $size = $w->getStaticInt();   // sync — no scope needed
        self::assertSame(42, $size);
    }

    #[Test]
    public function unknownMethodThrowsAolException(): void
    {
        $this->expectException(\Aol\Exception\AolException::class);
        $w = Aol::wrap(WrapTarget::class);
        /** @phpstan-ignore method.notFound */
        $w->doesNotExist();
    }

    #[Test]
    public function pendingArgsAreResolvedAutomatically(): void
    {
        $result = Aol::scope(function () {
            $w = Aol::wrap(WrapTarget::class);
            $a = Aol::async(fn () => 10);
            $b = Aol::async(fn () => 20);
            return $w->add($a, $b);
        });
        self::assertSame(30, $result);
    }
}

class WrapTarget
{
    #[Async]
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function getStaticInt(): int
    {
        return 42;
    }
}
