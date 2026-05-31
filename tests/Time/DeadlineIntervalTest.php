<?php

declare(strict_types=1);

namespace Aol\Tests\Time;

use Aol\Exception\AolTimeoutException;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeadlineIntervalTest extends TestCase
{
    #[Test]
    public function deadlineReturnsBodyResult(): void
    {
        $result = Time::deadline(1.0, fn () => 'ok');
        self::assertSame('ok', $result);
    }

    #[Test]
    public function deadlineFiresWhenBodyTooSlow(): void
    {
        $this->expectException(AolTimeoutException::class);
        Time::deadline(0.05, fn () => Time::sleep(10));
    }

    // Time::interval tests deferred along with the implementation
    // (Turn 8 — needs background-task scope registration).
}
