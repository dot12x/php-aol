<?php

declare(strict_types=1);

namespace Aol\Tests\Aol;

use Aol\Aol;
use Aol\Exception\AolScopeException;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OutsideScopeTest extends TestCase
{
    #[Test]
    public function asyncOutsideScopeThrows(): void
    {
        $this->expectException(AolScopeException::class);
        Aol::async(fn () => 1);
    }

    #[Test]
    public function timeSleepOutsideScopeIsAllowed(): void
    {
        // Time::sleep deliberately works without a scope (no cancellation).
        Time::sleep(0.01);
        $this->expectNotToPerformAssertions();
    }
}
