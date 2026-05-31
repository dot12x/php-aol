<?php

declare(strict_types=1);

namespace Aol\Internal;

use Aol\Aol;
use Aol\Attribute\Restart;
use Aol\Attribute\Retry;
use Aol\File;
use Aol\Time;
use Amp\CancelledException;
use Aol\Event\Awake;
use Aol\Event\Crash;
use Aol\Event\Restart as RestartEvent;
use Aol\Event\RetryAttempted;
use Aol\Event\Sleep;
use Aol\Exception\AolException;
use Aol\Exception\AolRestartLimitException;
use Aol\Exception\AolWrapException;
use Aol\Pending;
use Amp\Future;
use function Amp\async;
use function Amp\now;

/**
 * @internal The animated proxy returned by Aol::wrap().
 *
 * Owns a fixed pool of N instances. Routes async method calls to the
 * least-busy instance and returns Pending. Sync methods pass through
 * to the first instance directly.
 *
 * @template T of object
 * @mixin T
 */
final class Wrapper
{
    /** @var array<int, int> instance index → current in-flight count */
    private array $inflight;

    /** @var array<int, object> mutable: restart replaces entries in place */
    private array $instances;

    /** @var list<float> timestamps of recent restarts (rolling window) */
    private array $restartTimestamps = [];

    private bool $dead = false;

    /** @var list<string> Revolt watcher IDs for signal/file-watch handlers — cancelled in sleepAll. */
    private array $watcherIds = [];

    /** @var array<int, \Aol\Process\Spawned> instance index → live child process */
    private array $children = [];

    /** @var list<\Aol\WebSocket\Connection> live WebSocket connections, closed in sleepAll */
    private array $wsConnections = [];

    /**
     * @param ClassMetadata<T> $metadata
     * @param list<T> $instances
     * @param (\Closure(): T)|null $factory  Required for #[Restart]; null = restart impossible.
     */
    public function __construct(
        private readonly ClassMetadata $metadata,
        array $instances,
        private readonly ?\Closure $factory = null,
    ) {
        $this->instances = $instances;
        $this->inflight = \array_fill(0, \count($instances), 0);
    }

    /**
     * @param array<int|string, mixed> $args
     * @return Pending<mixed>|mixed
     */
    public function __call(string $method, array $args): mixed
    {
        if (!$this->metadata->hasMethod($method)) {
            throw new AolException(
                "Method '{$method}' is not declared on wrapped class '{$this->metadata->className}'."
            );
        }

        if (!$this->metadata->isAsync($method)) {
            return $this->instances[0]->{$method}(...$args);
        }

        if ($this->dead) {
            throw new AolException(
                "Wrapper for {$this->metadata->className} is dead — restart limit was exceeded."
            );
        }

        $scope = ScopeStack::mustCurrent();
        $methodTimeout = $this->metadata->timeoutFor($method);
        $retry = $this->metadata->retryFor($method);

        $idx = $this->leastBusyIndex();
        $this->inflight[$idx]++;
        $instance = $this->instances[$idx];
        $inflight = &$this->inflight;
        $self = $this;

        $className = $this->metadata->className;
        $wrapped = static function () use ($self, $instance, $method, $args, $scope, $methodTimeout, $retry, $className, &$inflight, $idx): mixed {
            ScopeStack::push($scope);
            try {
                $cancellation = $scope->cancellation();
                $resolvedArgs = [];
                foreach ($args as $k => $arg) {
                    $resolvedArgs[$k] = $arg instanceof Pending
                        ? $arg->internalFuture()->await($cancellation)
                        : $arg;
                }
                try {
                    return self::callWithRetryAndTimeout($className, $instance, $method, $resolvedArgs, $methodTimeout, $retry);
                } catch (\Throwable $e) {
                    Aol::emit(new Crash(
                        className: $className,
                        method: $method,
                        instanceIndex: $idx,
                        error: $e,
                        at: now(),
                    ));
                    $self->maybeRestart($idx, $e);
                    throw $e;
                }
            } finally {
                ScopeStack::pop($scope);
                $inflight[$idx]--;
            }
        };

        $future = async($wrapped);
        $pending = new Pending($future, $scope);
        $scope->register($pending);
        return $pending;
    }

    /**
     * @internal Called from the async wrapper when a method's final
     * attempt failed. Replaces the crashed instance with a fresh one
     * (best-effort OnSleep on the old, mandatory OnAwake on the new).
     *
     * If the restart rate limit is exceeded, marks the wrapper "dead"
     * and surfaces AolRestartLimitException as the *cause* of the
     * original failure on its next bubble-up — but we don't replace
     * the original $original here, we just mark dead so future calls
     * fail loudly.
     */
    public function maybeRestart(int $idx, \Throwable $original): void
    {
        $restart = $this->metadata->restart;
        if ($restart === null || $this->factory === null) {
            return;
        }

        $now = \microtime(true);
        $cutoff = $now - (float) $restart->within;
        $this->restartTimestamps = \array_values(
            \array_filter($this->restartTimestamps, static fn (float $ts): bool => $ts >= $cutoff)
        );

        if (\count($this->restartTimestamps) >= $restart->max) {
            $this->dead = true;
            return;
        }

        $this->restartTimestamps[] = $now;

        $crashed = $this->instances[$idx];
        foreach ($this->metadata->sleepMethods as $sleepMethod) {
            try {
                $crashed->{$sleepMethod}();
            } catch (\Throwable) {
            }
        }

        $factory = $this->factory;
        $new = $factory();
        foreach ($this->metadata->awakeMethods as $awakeMethod) {
            $new->{$awakeMethod}();
        }

        $this->instances[$idx] = $new;

        Aol::emit(new RestartEvent(
            className: $this->metadata->className,
            instanceIndex: $idx,
            cause: $original,
            at: now(),
        ));
    }

    public function isDead(): bool
    {
        return $this->dead;
    }

    /**
     * Invoke the method once (with optional per-method timeout) and
     * retry per the Retry policy if it throws. Bails on scope
     * cancellation between attempts.
     *
     * @param array<int|string, mixed> $args
     */
    private static function callWithRetryAndTimeout(
        string $className,
        object $instance,
        string $method,
        array $args,
        int|float|null $methodTimeout,
        ?Retry $retry,
    ): mixed {
        $maxAttempts = $retry !== null ? 1 + $retry->times : 1;
        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                if ($methodTimeout !== null) {
                    return Aol::scope(
                        timeout: (float) $methodTimeout,
                        body: static fn (): mixed => $instance->{$method}(...$args),
                    );
                }
                return $instance->{$method}(...$args);
            } catch (\Throwable $e) {
                $lastError = $e;
                if ($attempt >= $maxAttempts || $retry === null || !$retry->shouldRetry($e)) {
                    throw $e;
                }

                $scope = ScopeStack::mustCurrent();
                $scope->cancellation()->throwIfRequested();

                $delay = $retry->delayFor($attempt);

                Aol::emit(new RetryAttempted(
                    className: $className,
                    method: $method,
                    attempt: $attempt,
                    nextDelay: $delay,
                    error: $e,
                    at: now(),
                ));

                if ($delay > 0) {
                    $scope->clock()->sleep($delay, $scope->cancellation());
                }
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }
        throw new \LogicException('Retry loop exited without value or error.');
    }

    public function __get(string $name): mixed
    {
        return $this->instances[0]->{$name};
    }

    /**
     * @internal Run #[OnAwake] on every instance in parallel. Called
     * from Aol::wrap() right after construction. Wait for all hooks
     * to complete; if any threw, raise AolWrapException with the
     * first error.
     */
    public function awakeAll(): void
    {
        if (\count($this->metadata->awakeMethods) > 0) {
            $futures = [];
            foreach ($this->instances as $instance) {
                foreach ($this->metadata->awakeMethods as $method) {
                    $futures[] = async(static fn () => $instance->{$method}());
                }
            }

            [$errors, ] = Future\awaitAll($futures);
            if (\count($errors) > 0) {
                $first = \reset($errors);
                throw new AolWrapException(
                    \sprintf('OnAwake failed for %s: %s', $this->metadata->className, $first->getMessage()),
                    previous: $first,
                );
            }
        }

        $this->startTicks();
        $this->startSignals();
        $this->startFileWatchers();
        $this->startProcesses();
        $this->startSseSubscriptions();
        $this->startWebSocket();

        Aol::emit(new Awake(
            className: $this->metadata->className,
            poolSize: \count($this->instances),
            at: now(),
        ));
    }

    /**
     * Launch one background fiber per (instance × tick method × interval).
     * Each fiber sleeps for the configured period, invokes the method, then
     * repeats. The loop exits naturally when the scope cancels and
     * Time::sleep throws CancelledException.
     *
     * No-op when the class has no #[OnTick] methods or no active scope.
     */
    private function startTicks(): void
    {
        if (\count($this->metadata->tickMethods) === 0) {
            return;
        }
        $scope = ScopeStack::current();
        if ($scope === null) {
            return;
        }
        foreach ($this->instances as $instance) {
            foreach ($this->metadata->tickMethods as $method => $intervals) {
                foreach ($intervals as $every) {
                    Aol::asyncBackground(static function () use ($instance, $method, $every, $scope): void {
                        $cancellation = $scope->cancellation();
                        while (!$cancellation->isRequested()) {
                            try {
                                $scope->clock()->sleep((float) $every, $cancellation);
                            } catch (CancelledException) {
                                return;
                            }
                            $instance->{$method}();
                        }
                    });
                }
            }
        }
    }

    /**
     * Register one Revolt onSignal watcher per (instance × method × signal).
     * The watcher IDs are tracked in $watcherIds and cancelled in stopWatchers().
     * No-op when the class has no #[OnSignal] methods.
     */
    private function startSignals(): void
    {
        if (\count($this->metadata->signalMethods) === 0) {
            return;
        }
        foreach ($this->instances as $instance) {
            foreach ($this->metadata->signalMethods as $method => $signals) {
                foreach ($signals as $sig) {
                    $this->watcherIds[] = \Revolt\EventLoop::onSignal(
                        $sig,
                        static function () use ($instance, $method): void {
                            $instance->{$method}();
                        },
                    );
                }
            }
        }
    }

    /**
     * Launch one background fiber per (instance × file-watch method × spec).
     * Each fiber iterates File::watch(), invoking the method for each FileEvent.
     * The loop exits when the scope cancels and File::watch returns naturally.
     *
     * No-op when the class has no #[OnFileChange] methods or no active scope.
     * The recursive field in each spec is intentionally unused — File::watch
     * does not yet support recursive watching; that will be wired in a later phase.
     */
    private function startFileWatchers(): void
    {
        if (\count($this->metadata->fileWatchMethods) === 0) {
            return;
        }
        $scope = ScopeStack::current();
        if ($scope === null) {
            return;
        }
        foreach ($this->instances as $instance) {
            foreach ($this->metadata->fileWatchMethods as $method => $specs) {
                foreach ($specs as $spec) {
                    Aol::asyncBackground(static function () use ($instance, $method, $spec): void {
                        foreach (File::watch($spec['path'], $spec['pollInterval']) as $event) {
                            $instance->{$method}($event);
                        }
                    });
                }
            }
        }
    }

    /**
     * Launch one background fiber per (instance × #[OnSse] method × URL).
     * Each fiber opens an Http::sse stream and invokes the handler with
     * every parsed SseEvent. The loop exits naturally when the scope
     * cancels and the underlying response body closes.
     *
     * No-op when the class has no #[OnSse] methods or no active scope.
     */
    private function startSseSubscriptions(): void
    {
        if (\count($this->metadata->sseMethods) === 0) {
            return;
        }
        $scope = ScopeStack::current();
        if ($scope === null) {
            return;
        }
        foreach ($this->instances as $instance) {
            foreach ($this->metadata->sseMethods as $method => $urls) {
                foreach ($urls as $url) {
                    Aol::asyncBackground(static function () use ($instance, $method, $url): void {
                        foreach (\Aol\Http::sse($url) as $event) {
                            $instance->{$method}($event);
                        }
                    });
                }
            }
        }
    }

    /**
     * Open one WebSocket per pool instance per class-level #[WebSocket(url)] spec.
     * Hydrates the #[WsConnection] property (if any), fires #[OnOpen] synchronously,
     * then drives the receive loop on a background fiber that dispatches each frame
     * to every #[OnMessage] handler. When the loop ends, every #[OnClose] handler is
     * invoked once with (null, null). Connections are closed in sleepAll().
     *
     * No-op when the class has no #[WebSocket] attribute or no active scope.
     */
    private function startWebSocket(): void
    {
        $spec = $this->metadata->wsSpec;
        if ($spec === null) {
            return;
        }
        $scope = ScopeStack::current();
        if ($scope === null) {
            return;
        }
        $cancellation = $scope->cancellation();
        foreach ($this->instances as $instance) {
            $conn = new \Aol\WebSocket\Connection(\Aol\Internal\WebSocket\Client::connect($spec->url));
            $this->wsConnections[] = $conn;

            $cancellation->subscribe(static function () use ($conn): void {
                if ($conn->isAlive) {
                    try {
                        $conn->close();
                    } catch (\Throwable) {
                    }
                }
            });

            $propName = $this->metadata->wsConnectionProperty;
            if ($propName !== null) {
                $rp = new \ReflectionProperty($instance, $propName);
                $rp->setValue($instance, $conn);
            }
            foreach ($this->metadata->wsOpenMethods as $m) {
                $instance->{$m}();
            }

            $messageMethods = $this->metadata->wsMessageMethods;
            $closeMethods = $this->metadata->wsCloseMethods;

            Aol::asyncBackground(static function () use ($conn, $instance, $messageMethods, $closeMethods): void {
                foreach ($conn->messages() as $message) {
                    foreach ($messageMethods as $m) {
                        $instance->{$m}($message);
                    }
                }
                foreach ($closeMethods as $m) {
                    $instance->{$m}(null, null);
                }
            });
        }
    }

    /**
     * Close every live WebSocket connection. Called at the start of sleepAll()
     * so the receive loops terminate before #[OnSleep] runs.
     */
    private function stopWebSockets(): void
    {
        foreach ($this->wsConnections as $c) {
            if ($c->isAlive) {
                try {
                    $c->close();
                } catch (\Throwable) {
                }
            }
        }
        $this->wsConnections = [];
    }

    /**
     * Spawn one child process per instance for the class-level #[Process] spec.
     * Background fibers stream stdout/stderr lines to their respective annotated
     * methods and a third fiber waits for exit, calls #[OnExit] handlers, and
     * optionally respawns when restart=true and the scope is still live.
     *
     * No-op when the class has no #[Process] attribute or no active scope.
     */
    private function startProcesses(): void
    {
        $spec = $this->metadata->processSpec;
        if ($spec === null) {
            return;
        }
        $scope = ScopeStack::current();
        if ($scope === null) {
            return;
        }
        foreach ($this->instances as $idx => $instance) {
            $this->spawnChild($idx, $instance, $spec, $scope);
        }
    }

    /**
     * Spawn a single child process for the given instance, wire stdout/stderr
     * streaming fibers, and an exit-watcher fiber that optionally restarts.
     */
    public function spawnChild(
        int $idx,
        object $instance,
        \Aol\Process\Attribute\Process $spec,
        Scope $scope,
    ): void {
        $child = \Aol\Process::spawn($spec->command);
        $this->children[$idx] = $child;
        $stdoutMethods = $this->metadata->stdoutMethods;
        $stderrMethods = $this->metadata->stderrMethods;
        $exitMethods = $this->metadata->exitMethods;
        $self = $this;

        if (\count($stdoutMethods) > 0) {
            Aol::asyncBackground(static function () use ($child, $instance, $stdoutMethods): void {
                foreach ($child->stdout() as $line) {
                    foreach ($stdoutMethods as $m) {
                        $instance->{$m}($line);
                    }
                }
            });
        }
        if (\count($stderrMethods) > 0) {
            Aol::asyncBackground(static function () use ($child, $instance, $stderrMethods): void {
                foreach ($child->stderr() as $line) {
                    foreach ($stderrMethods as $m) {
                        $instance->{$m}($line);
                    }
                }
            });
        }

        Aol::asyncBackground(static function () use ($child, $instance, $exitMethods, $spec, $self, $idx, $scope): void {
            $code = $child->wait();
            foreach ($exitMethods as $m) {
                $instance->{$m}($code);
            }
            if ($spec->restart && !$scope->cancellation()->isRequested()) {
                $self->spawnChild($idx, $instance, $spec, $scope);
            }
        });
    }

    /**
     * Send SIGTERM to every live child process and clear the children map.
     * Called at the start of sleepAll() so processes are torn down before
     * #[OnSleep] callbacks run.
     */
    private function stopProcesses(): void
    {
        foreach ($this->children as $child) {
            if ($child->isRunning()) {
                try {
                    $child->kill(\SIGTERM);
                } catch (\Throwable) {
                }
            }
        }
        $this->children = [];
    }

    /**
     * Cancel all Revolt watchers accumulated in $watcherIds and reset the list.
     * After Revolt cancels the last watcher for a signal it restores SIG_DFL,
     * which would terminate the process on arrival. We override that with
     * SIG_IGN so a late in-flight signal is silently discarded rather than
     * causing an unintended process exit.
     * Called at the very start of sleepAll() so signal handlers are torn down
     * before any #[OnSleep] callbacks run.
     */
    private function stopWatchers(): void
    {
        $signals = [];
        if (\count($this->metadata->signalMethods) > 0 && \function_exists('pcntl_signal')) {
            foreach ($this->metadata->signalMethods as $methodSignals) {
                foreach ($methodSignals as $sig) {
                    $signals[$sig] = true;
                }
            }
        }

        foreach ($this->watcherIds as $id) {
            \Revolt\EventLoop::cancel($id);
        }
        $this->watcherIds = [];

        foreach (\array_keys($signals) as $sig) {
            \pcntl_signal($sig, \SIG_IGN);
        }
    }

    /**
     * @internal Run #[OnSleep] on every instance in parallel. Called
     * by Scope at close. Exceptions are swallowed (best-effort
     * cleanup; the scope's own collection logic already decided
     * whether to surface an error).
     */
    public function sleepAll(): void
    {
        $this->stopWatchers();
        $this->stopProcesses();
        $this->stopWebSockets();

        if (\count($this->metadata->sleepMethods) > 0) {
            $futures = [];
            foreach ($this->instances as $instance) {
                foreach ($this->metadata->sleepMethods as $method) {
                    $futures[] = async(static fn () => $instance->{$method}());
                }
            }
            Future\awaitAll($futures);
        }

        Aol::emit(new Sleep(
            className: $this->metadata->className,
            poolSize: \count($this->instances),
            at: now(),
        ));
    }

    /**
     * @internal Test introspection only.
     * @return list<int>
     */
    public function inflightSnapshot(): array
    {
        return \array_values($this->inflight);
    }

    private function leastBusyIndex(): int
    {
        $min = $this->inflight[0];
        $idx = 0;
        $count = \count($this->inflight);
        for ($i = 1; $i < $count; $i++) {
            if ($this->inflight[$i] < $min) {
                $min = $this->inflight[$i];
                $idx = $i;
            }
        }
        return $idx;
    }
}
