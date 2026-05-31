<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CrashCancelsDependentsTest extends TestCase
{
    #[Test]
    public function dependentClosureNeverRunsWhenItsDepFails(): void
    {
        $reached = false;

        try {
            Aol::scope(function () use (&$reached) {
                $a = Aol::async(fn () => throw new \RuntimeException('source failed'));
                $b = Aol::async(function (int $x) use (&$reached) {
                    $reached = true;
                    return $x + 1;
                }, $a);
                return $b;
            });
            self::fail('Expected RuntimeException.');
        } catch (\RuntimeException $e) {
            self::assertSame('source failed', $e->getMessage());
        }

        self::assertFalse($reached, 'Dependent closure should never have been invoked.');
    }

    #[Test]
    public function bodyExceptionCancelsInFlightPendings(): void
    {
        $reached = false;

        try {
            Aol::scope(function () use (&$reached) {
                Aol::async(function () use (&$reached) {
                    \Aol\Time::sleep(10);
                    $reached = true;
                    return 'never';
                });
                throw new \DomainException('body blew up');
            });
        } catch (\DomainException $e) {
            self::assertSame('body blew up', $e->getMessage());
        }

        self::assertFalse($reached, 'Async sibling should have been cancelled when body threw.');
    }
}
