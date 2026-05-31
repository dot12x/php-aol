<?php

declare(strict_types=1);

namespace Aol\Tests\Pending;

use Aol\Aol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PendingChainTest extends TestCase
{
    #[Test]
    public function magicGetOnArrayResolvesToKey(): void
    {
        $result = Aol::scope(function () {
            $pending = Aol::async(fn () => ['size' => 800, 'format' => 'jpg']);
            return $pending->size;
        });
        self::assertSame(800, $result);
    }

    #[Test]
    public function magicGetOnObjectResolvesToProperty(): void
    {
        $result = Aol::scope(function () {
            $pending = Aol::async(fn () => (object) ['name' => 'sattorbek', 'age' => 25]);
            return $pending->name;
        });
        self::assertSame('sattorbek', $result);
    }

    #[Test]
    public function magicCallOnObjectInvokesMethod(): void
    {
        $obj = new class {
            public function double(int $x): int
            {
                return $x * 2;
            }
        };

        $result = Aol::scope(function () use ($obj) {
            $pending = Aol::async(fn () => $obj);
            return $pending->double(21);
        });
        self::assertSame(42, $result);
    }

    #[Test]
    public function magicCallResolvesPendingArguments(): void
    {
        $obj = new class {
            public function concat(string $a, string $b, string $c): string
            {
                return "{$a}-{$b}-{$c}";
            }
        };

        $result = Aol::scope(function () use ($obj) {
            $pending = Aol::async(fn () => $obj);
            $a = Aol::async(fn () => 'X');
            return $pending->concat($a, 'Y', Aol::async(fn () => 'Z'));
        });
        self::assertSame('X-Y-Z', $result);
    }

    #[Test]
    public function chainedGetReturnsAnotherPending(): void
    {
        $result = Aol::scope(function () {
            $pending = Aol::async(fn () => ['nested' => ['deep' => 'value']]);
            // pending->nested is Pending<array>, then ['deep'] needs another chain
            // (string array key access via __get works for assoc arrays)
            return $pending->nested;
        });
        self::assertSame(['deep' => 'value'], $result);
    }

    #[Test]
    public function magicGetOnScalarThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        Aol::scope(function () {
            $pending = Aol::async(fn () => 42);
            return $pending->somefield;
        });
    }

    #[Test]
    public function magicCallOnScalarThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        Aol::scope(function () {
            $pending = Aol::async(fn () => 42);
            return $pending->someMethod();
        });
    }
}
