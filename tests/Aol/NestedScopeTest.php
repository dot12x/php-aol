<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NestedScopeTest extends TestCase
{
    #[Test]
    public function nestedScopeReturnsInnerValue(): void
    {
        $x = Aol::scope(fn () => Aol::scope(fn () => Aol::async(fn () => 42)));
        self::assertSame(42, $x);
    }

    #[Test]
    public function innerScopeIsActiveInsideOuter(): void
    {
        $result = Aol::scope(function () {
            // Outer scope active here; inner scope opens, runs, closes,
            // then we're back to outer.
            $inner = Aol::scope(fn () => Aol::async(fn () => 'inner'));
            return Aol::async(fn (string $x) => "outer-{$x}", Aol::async(fn () => $inner));
        });
        self::assertSame('outer-inner', $result);
    }
}
