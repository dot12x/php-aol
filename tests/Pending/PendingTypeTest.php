<?php

declare(strict_types=1);

namespace Aol\Tests\Pending;

use Aol\Pending;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PendingTypeTest extends TestCase
{
    #[Test]
    public function pendingExposesNoAwaitOrSimilarPublicMethods(): void
    {
        $banned = ['await', 'then', 'catch', 'finally', 'value', 'get', 'unwrap', 'resolve', 'yield', 'spawn'];
        $reflection = new \ReflectionClass(Pending::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = strtolower($method->getName());
            self::assertNotContains(
                $name,
                $banned,
                "Pending must not expose a public method named '{$method->getName()}' — banned by design."
            );
        }
    }

    #[Test]
    public function pendingIsFinalAndReadonly(): void
    {
        $reflection = new \ReflectionClass(Pending::class);
        self::assertTrue($reflection->isFinal(), 'Pending must be final.');
        self::assertTrue($reflection->isReadOnly(), 'Pending must be a readonly class (PHP 8.4).');
    }

    #[Test]
    public function pendingHasNoPublicWritableProperties(): void
    {
        $reflection = new \ReflectionClass(Pending::class);
        foreach ($reflection->getProperties() as $property) {
            self::assertFalse(
                $property->isPublic() && !$property->isReadOnly(),
                "Pending property '{$property->getName()}' must not be public-writable."
            );
        }
    }
}
