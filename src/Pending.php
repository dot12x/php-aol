<?php

declare(strict_types=1);

namespace Aol;

use Aol\Internal\Scope;
use Aol\Support\Arr;
use Amp\Future;
use function Amp\async;

/**
 * A value not yet here. Returned by Aol::async() and by chaining
 * `->field` / `->method(...)` on another Pending.
 *
 * Pass a Pending to another async call (auto-graph), or return it from
 * Aol::scope — the scope resolves all Pendings at close time. There is
 * deliberately NO await/then/get/value/unwrap method on this class.
 *
 * Chaining: `$pending->field` (magic __get) and `$pending->method(...)`
 * (magic __call) each return a new Pending<X> that resolves lazily
 * after the upstream Pending resolves.
 *
 * @template-covariant T
 */
final readonly class Pending
{
    /**
     * @internal Constructed only by Aol::async or Pending chaining.
     *
     * @param Future<T> $future
     */
    public function __construct(
        private Future $future,
        private Scope $owner,
    ) {
    }

    /**
     * @internal Used by Scope to materialize the value at scope-close.
     *
     * @return Future<T>
     */
    public function internalFuture(): Future
    {
        return $this->future;
    }

    /**
     * @internal Used by Scope for ownership assertions.
     */
    public function owner(): Scope
    {
        return $this->owner;
    }

    /**
     * Magic field access: $pending->field returns a new Pending that
     * resolves to $value->field (or $value['field'] for arrays).
     *
     * @return Pending<mixed>
     */
    public function __get(string $name): self
    {
        $upstream = $this->future;
        $scope = $this->owner;
        $cancellation = $scope->cancellation();

        $wrapped = static function () use ($upstream, $cancellation, $name): mixed {
            $resolved = $upstream->await($cancellation);
            if (\is_object($resolved)) {
                return $resolved->{$name};
            }
            if (\is_array($resolved)) {
                return Arr::from($resolved)->get($name);
            }
            throw new \LogicException(
                "Pending chain: cannot access ->{$name} on non-object/non-array value of type " . \get_debug_type($resolved)
            );
        };

        return self::chained($scope, $wrapped);
    }

    /**
     * Magic method call: $pending->method(...$args) returns a new
     * Pending that resolves to $value->method(...$args). Args may
     * themselves be Pendings — they're resolved before the call.
     *
     * @param array<int|string, mixed> $args
     * @return Pending<mixed>
     */
    public function __call(string $method, array $args): self
    {
        $upstream = $this->future;
        $scope = $this->owner;
        $cancellation = $scope->cancellation();

        $wrapped = static function () use ($upstream, $cancellation, $method, $args): mixed {
            $resolved = $upstream->await($cancellation);
            if (!\is_object($resolved)) {
                throw new \LogicException(
                    "Pending chain: cannot call ->{$method}() on non-object value of type " . \get_debug_type($resolved)
                );
            }

            $resolvedArgs = [];
            foreach ($args as $k => $arg) {
                $resolvedArgs[$k] = $arg instanceof Pending
                    ? $arg->internalFuture()->await($cancellation)
                    : $arg;
            }

            return $resolved->{$method}(...$resolvedArgs);
        };

        return self::chained($scope, $wrapped);
    }

    /**
     * @return Pending<mixed>
     */
    private static function chained(Scope $scope, \Closure $wrapped): self
    {
        $future = async($wrapped);
        $pending = new self($future, $scope);
        $scope->register($pending);
        return $pending;
    }
}
