<?php

declare(strict_types=1);

namespace Aol\Tests\Internal;

use Aol\Aol;
use Aol\Internal\ScopeStack;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScopeStackFiberLocalTest extends TestCase
{
    #[Test]
    public function activeScopeIsPushedInsideAsyncFiber(): void
    {
        $result = Aol::scope(function () {
            $outer = ScopeStack::current();
            self::assertNotNull($outer);

            return Aol::async(function () use ($outer) {
                // We're now inside a spawned fiber. Aol::async pushed
                // the outer scope into this fiber's ScopeStack, so we
                // can call Aol::async / Time::sleep here.
                self::assertSame($outer, ScopeStack::current());

                return 'ok';
            });
        });

        self::assertSame('ok', $result);
    }

    #[Test]
    public function nestedScopeBecomesInnermostInsideItsBody(): void
    {
        Aol::scope(function () {
            $outer = ScopeStack::current();

            Aol::scope(function () use ($outer) {
                $inner = ScopeStack::current();
                self::assertNotSame($outer, $inner);
                self::assertNotNull($inner);
            });

            // Back to outer scope after nested closes.
            self::assertSame($outer, ScopeStack::current());
        });
    }

    #[Test]
    public function asyncOutsideScopeAfterScopeClosedThrows(): void
    {
        Aol::scope(fn () => null);
        self::assertNull(ScopeStack::current(), 'Scope should be popped after Aol::scope returns.');
    }
}
