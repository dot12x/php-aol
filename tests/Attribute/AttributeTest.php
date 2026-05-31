<?php

declare(strict_types=1);

namespace Aol\Tests\Attribute;

use Aol\Attribute\Async;
use Aol\Attribute\Worker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeTest extends TestCase
{
    #[Test]
    public function asyncIsMethodAttribute(): void
    {
        $reflection = new \ReflectionClass(Async::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();
        self::assertSame(\Attribute::TARGET_METHOD, $attribute->flags);
    }

    #[Test]
    public function workerIsClassAttribute(): void
    {
        $reflection = new \ReflectionClass(Worker::class);
        $attribute = $reflection->getAttributes(\Attribute::class)[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    #[Test]
    public function workerDefaultsPoolOneQueue1024(): void
    {
        $w = new Worker();
        self::assertSame(1, $w->pool);
        self::assertSame(1024, $w->queue);
    }

    #[Test]
    public function workerAcceptsNamedArgs(): void
    {
        $w = new Worker(pool: 4, queue: 100);
        self::assertSame(4, $w->pool);
        self::assertSame(100, $w->queue);
    }

    #[Test]
    public function bothAttributesAreFinalReadonly(): void
    {
        foreach ([Async::class, Worker::class] as $class) {
            $r = new \ReflectionClass($class);
            self::assertTrue($r->isFinal(), "{$class} must be final.");
            self::assertTrue($r->isReadOnly(), "{$class} must be readonly.");
        }
    }
}
