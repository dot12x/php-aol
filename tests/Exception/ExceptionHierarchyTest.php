<?php

declare(strict_types=1);

namespace Aol\Tests\Exception;

use Aol\Exception\AolCancelledException;
use Aol\Exception\AolException;
use Aol\Exception\AolScopeException;
use Aol\Exception\AolTimeoutException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function aolExceptionIsRuntimeException(): void
    {
        self::assertInstanceOf(\RuntimeException::class, new AolException('x'));
    }

    #[Test]
    public function aolTimeoutExceptionIsAolException(): void
    {
        self::assertInstanceOf(AolException::class, new AolTimeoutException('x'));
    }

    #[Test]
    public function aolCancelledExceptionIsAolException(): void
    {
        self::assertInstanceOf(AolException::class, new AolCancelledException('x'));
    }

    #[Test]
    public function aolScopeExceptionIsAolException(): void
    {
        self::assertInstanceOf(AolException::class, new AolScopeException('x'));
    }
}
