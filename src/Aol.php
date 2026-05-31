<?php

declare(strict_types=1);

namespace Aol;

use Aol\Event\AolEvent;
use Aol\Internal\ReflectionCache;
use Aol\Internal\Scope;
use Aol\Internal\ScopeStack;
use Aol\Internal\Wrapper;
use Aol\Time\RevoltClock;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use function Amp\async;

/**
 * The user-facing facade. The only types users normally see are this
 * class, {@see Pending}, {@see Time}, and the exceptions under
 * Aol\Exception.
 */
final class Aol
{
    private static ?ContainerInterface $container = null;
    private static ?LoggerInterface $logger = null;

    /** @var list<\Closure(AolEvent): void> */
    private static array $eventListeners = [];

    /**
     * Register a PSR-11 container. When set, Aol::wrap($classString)
     * resolves instances from the container (when has() returns true);
     * otherwise it falls back to `new $class(...$args)`.
     */
    public static function useContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    /**
     * Register a PSR-3 logger. Currently used to log listener
     * exceptions; future turns may add automatic warn/info events.
     */
    public static function useLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Register a listener for lifecycle events (Awake, Sleep, Crash,
     * Restart, RetryAttempted). Listeners are invoked synchronously
     * when the library emits an event; their exceptions are caught
     * and logged via the PSR-3 logger if one is set.
     *
     * @param callable(AolEvent): void $listener
     */
    public static function onEvent(callable $listener): void
    {
        self::$eventListeners[] = \Closure::fromCallable($listener);
    }

    /**
     * @internal Used by the runtime to fan an event out to listeners.
     */
    public static function emit(AolEvent $event): void
    {
        foreach (self::$eventListeners as $listener) {
            try {
                $listener($event);
            } catch (\Throwable $e) {
                if (self::$logger !== null) {
                    try {
                        self::$logger->error(
                            'AOL event listener threw: ' . $e->getMessage(),
                            ['event' => $event::class, 'exception' => $e],
                        );
                    } catch (\Throwable) {
                    }
                }
            }
        }
    }

    /**
     * @internal Test-only.
     */
    public static function clearContainer(): void
    {
        self::$container = null;
    }

    /**
     * @internal Test-only.
     */
    public static function clearEventListeners(): void
    {
        self::$eventListeners = [];
    }

    /**
     * @internal Test-only.
     */
    public static function clearLogger(): void
    {
        self::$logger = null;
    }

    /**
     * Open a scope. The body runs to completion; any Pending it created
     * (returned or discarded) is awaited before scope close. If any
     * Pending fails, the scope cancels the rest and rethrows.
     *
     * @template T
     * @param callable(): T $body
     * @return T
     */
    public static function scope(callable $body, ?float $timeout = null): mixed
    {
        $parent = ScopeStack::current();
        $scope = new Scope(
            parent: $parent,
            clock: new RevoltClock(),
            timeout: $timeout,
        );
        ScopeStack::push($scope);

        try {
            return $scope->collect($body);
        } finally {
            ScopeStack::pop($scope);
        }
    }

    /**
     * Schedule a closure to run inside the current scope. Returns a
     * Pending you can pass to other async calls or return from the scope.
     *
     * Each dep that is a Pending is resolved to its value before $fn is
     * invoked; plain values are passed through unchanged. Positional
     * order is preserved.
     *
     * Note on generics: a precise `Pending<T>` return-type inference from
     * an arbitrary user closure with mixed Pending/non-Pending args is
     * deferred to a custom PHPStan extension in Turn 2. Until then, the
     * return is `Pending<mixed>`; users narrow with `@var Pending<X>` at
     * the call site if needed.
     *
     * @param callable $fn
     * @param mixed ...$deps
     * @return Pending<mixed>
     */
    public static function async(callable $fn, mixed ...$deps): Pending
    {
        $scope = ScopeStack::mustCurrent();
        $cancellation = $scope->cancellation();

        $wrapped = static function () use ($fn, $deps, $scope, $cancellation): mixed {
            ScopeStack::push($scope);
            try {
                $resolved = [];
                foreach ($deps as $i => $dep) {
                    $resolved[$i] = $dep instanceof Pending
                        ? $dep->internalFuture()->await($cancellation)
                        : $dep;
                }
                return $fn(...$resolved);
            } finally {
                ScopeStack::pop($scope);
            }
        };

        $future = async($wrapped);
        $pending = new Pending($future, $scope);
        $scope->register($pending);
        return $pending;
    }

    /**
     * Like async(), but the returned Pending is treated as a background
     * task: the scope does not wait for it to finish on its own;
     * instead, when the scope closes (normal or error), the scope
     * cancels itself and silently drains any background Pendings.
     *
     * Use for ticks, interval timers, signal listeners — anything that
     * naturally runs "until the scope ends".
     *
     * @param callable $fn
     * @param mixed ...$deps
     * @return Pending<mixed>
     */
    public static function asyncBackground(callable $fn, mixed ...$deps): Pending
    {
        $scope = ScopeStack::mustCurrent();
        $cancellation = $scope->cancellation();

        $wrapped = static function () use ($fn, $deps, $scope, $cancellation): mixed {
            ScopeStack::push($scope);
            try {
                $resolved = [];
                foreach ($deps as $i => $dep) {
                    $resolved[$i] = $dep instanceof Pending
                        ? $dep->internalFuture()->await($cancellation)
                        : $dep;
                }
                return $fn(...$resolved);
            } finally {
                ScopeStack::pop($scope);
            }
        };

        $future = async($wrapped);
        $pending = new Pending($future, $scope);
        $scope->registerBackground($pending);
        return $pending;
    }

    /**
     * Animate a class into a wrapped proxy. Methods annotated with
     * #[Async] return Pending; un-annotated methods pass through
     * synchronously.
     *
     * Accepts: a class string (we instantiate, optionally pool: N
     * eagerly via #[Worker(pool: N)]); an existing instance (pool=1
     * regardless of #[Worker]); or a factory closure (called N times
     * for pool=N).
     *
     * @template T of object
     * @param class-string<T>|T|\Closure(): T $target
     * @param mixed ...$args Constructor args, used only when $target is a class-string.
     * @return Wrapper<T>
     */
    public static function wrap(string|object $target, mixed ...$args): Wrapper
    {
        if ($target instanceof \Closure) {
            /** @var T $probe */
            $probe = ($target)();
            /** @var class-string<T> $class */
            $class = $probe::class;
            $metadata = ReflectionCache::for($class);
            /** @var list<T> $instances */
            $instances = [$probe];
            for ($i = 1; $i < $metadata->poolSize; $i++) {
                $instances[] = ($target)();
            }
            return self::finalizeWrap(new Wrapper($metadata, $instances, $target));
        }

        if (\is_object($target)) {
            $metadata = ReflectionCache::for($target::class);
            /** @var list<T> $instances */
            $instances = [$target];
            return self::finalizeWrap(new Wrapper($metadata, $instances, null));
        }

        $metadata = ReflectionCache::for($target);
        $factory = static fn (): object => self::instantiate($target, $args);
        /** @var list<T> $instances */
        $instances = [];
        for ($i = 0; $i < $metadata->poolSize; $i++) {
            $instances[] = $factory();
        }
        return self::finalizeWrap(new Wrapper($metadata, $instances, $factory));
    }

    /**
     * Run #[OnAwake] hooks (parallel, fail-fast). Register for
     * #[OnSleep] in the active scope if one exists.
     *
     * @template T of object
     * @param Wrapper<T> $wrapper
     * @return Wrapper<T>
     */
    private static function finalizeWrap(Wrapper $wrapper): Wrapper
    {
        $wrapper->awakeAll();

        $scope = ScopeStack::current();
        if ($scope !== null) {
            $scope->registerWrapper($wrapper);
        }

        return $wrapper;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<int|string, mixed> $args
     * @return T
     */
    private static function instantiate(string $class, array $args): object
    {
        if (self::$container !== null && self::$container->has($class)) {
            /** @var T */
            return self::$container->get($class);
        }
        return new $class(...$args);
    }
}
