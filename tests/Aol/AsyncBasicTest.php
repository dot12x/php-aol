<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsyncBasicTest extends TestCase
{
    #[Test]
    public function asyncReturnsValueViaScope(): void
    {
        $result = Aol::scope(fn () => Aol::async(fn () => 42));
        self::assertSame(42, $result);
    }

    #[Test]
    public function scopeReturnsArrayOfValues(): void
    {
        $result = Aol::scope(fn () => [
            Aol::async(fn () => 1),
            Aol::async(fn () => 2),
        ]);
        self::assertSame([1, 2], $result);
    }

    #[Test]
    public function scopeReturnsPlainValueUnchanged(): void
    {
        $result = Aol::scope(fn () => 'hello');
        self::assertSame('hello', $result);
    }

    #[Test]
    public function scopeReturnsAssocArrayWithPendings(): void
    {
        $result = Aol::scope(fn () => [
            'a' => Aol::async(fn () => 1),
            'b' => Aol::async(fn () => 2),
        ]);
        self::assertSame(['a' => 1, 'b' => 2], $result);
    }

    #[Test]
    public function scopeReturnsArrayMixingPendingAndPlain(): void
    {
        $result = Aol::scope(fn () => [
            'pending' => Aol::async(fn () => 42),
            'plain' => 'literal',
        ]);
        self::assertSame(['pending' => 42, 'plain' => 'literal'], $result);
    }
}
