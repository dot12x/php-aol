<?php

declare(strict_types=1);

namespace Aol\Internal;

use Aol\Exception\AolScopeException;
use Revolt\EventLoop\FiberLocal;

/**
 * @internal Active-scope discovery via FiberLocal. Inherits across
 * Amp\async-spawned child fibers automatically, which is exactly the
 * semantic we want: a closure running inside Aol::async can call
 * Aol::async itself and see the same active scope.
 */
final class ScopeStack
{
    /** @var FiberLocal<mixed>|null */
    private static ?FiberLocal $local = null;

    public static function current(): ?Scope
    {
        $value = self::local()->get();
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Scope) {
            throw new \LogicException('ScopeStack FiberLocal contained an unexpected value.');
        }
        return $value;
    }

    public static function mustCurrent(): Scope
    {
        $scope = self::current();
        if ($scope === null) {
            throw new AolScopeException(
                'No active scope. Aol::async must be called from inside Aol::scope.'
            );
        }
        return $scope;
    }

    public static function push(Scope $scope): void
    {
        self::local()->set($scope);
    }

    public static function pop(Scope $scope): void
    {
        self::local()->set($scope->parent());
    }

    /**
     * @return FiberLocal<mixed>
     */
    private static function local(): FiberLocal
    {
        if (self::$local === null) {
            $initializer = static function (): mixed {
                return null;
            };
            /** @var FiberLocal<mixed> $local */
            $local = new FiberLocal($initializer);
            self::$local = $local;
        }
        return self::$local;
    }
}
