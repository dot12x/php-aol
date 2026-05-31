<?php

declare(strict_types=1);

namespace Aol\Internal;

use Aol\Exception\AolTimeoutException;
use Aol\Pending;
use Aol\Time\Clock;
use Aol\Time\RevoltClock;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\Future;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;

/**
 * @internal The arena. Owns registered Pendings, the shared cancellation,
 * and the collect/drain loop that implements one_for_all crash policy.
 */
final class Scope
{
    private readonly DeferredCancellation $deferredCancellation;
    private readonly Cancellation $effectiveCancellation;

    /** @var array<int, Pending<mixed>> */
    private array $pendings = [];

    /** @var array<int, Pending<mixed>> */
    private array $backgroundPendings = [];

    /** @var list<Wrapper<object>> */
    private array $wrappers = [];

    /** @var list<string> Temporary paths created in this scope; deleted at close. */
    private array $tempPaths = [];

    private bool $cancelled = false;

    public function __construct(
        private readonly ?Scope $parent = null,
        private readonly Clock $clock = new RevoltClock(),
        ?float $timeout = null,
    ) {
        $this->deferredCancellation = new DeferredCancellation();

        $parts = [$this->deferredCancellation->getCancellation()];
        if ($parent !== null) {
            $parts[] = $parent->cancellation();
        }
        if ($timeout !== null) {
            $parts[] = new TimeoutCancellation($timeout);
        }

        $this->effectiveCancellation = \count($parts) === 1
            ? $parts[0]
            : new CompositeCancellation(...$parts);
    }

    public function parent(): ?Scope
    {
        return $this->parent;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function cancellation(): Cancellation
    {
        return $this->effectiveCancellation;
    }

    /**
     * @param Pending<mixed> $pending
     */
    public function register(Pending $pending): void
    {
        $this->pendings[\spl_object_id($pending)] = $pending;
    }

    /**
     * @param Pending<mixed> $pending
     */
    public function registerBackground(Pending $pending): void
    {
        $this->backgroundPendings[\spl_object_id($pending)] = $pending;
    }

    /**
     * @param Wrapper<object> $wrapper
     */
    public function registerWrapper(Wrapper $wrapper): void
    {
        $this->wrappers[] = $wrapper;
    }

    /**
     * Register a filesystem path to be removed when this scope closes.
     * Used by File::temp() and File::tempDir(). Best-effort: removal
     * errors are swallowed.
     */
    public function registerTempPath(string $path): void
    {
        $this->tempPaths[] = $path;
    }

    /**
     * Detach a previously-registered temp path (e.g. File::temp()->keep()).
     */
    public function unregisterTempPath(string $path): void
    {
        $this->tempPaths = \array_values(\array_filter(
            $this->tempPaths,
            static fn (string $p): bool => $p !== $path,
        ));
    }

    public function cancel(?\Throwable $cause = null): void
    {
        if ($this->cancelled) {
            return;
        }
        $this->cancelled = true;
        if (!$this->deferredCancellation->isCancelled()) {
            $this->deferredCancellation->cancel($cause);
        }
    }

    public function collect(callable $body): mixed
    {
        try {
            $bodyResult = $body();
        } catch (\Throwable $e) {
            $this->cancel($e);
            $this->drainSilently();
            $this->drainBackgroundSilently();
            $this->closeResources();
            throw self::asTimeoutIfApplicable($e);
        }

        $firstError = $this->drainAll();

        if ($firstError !== null) {
            $this->cancel($firstError);
            $this->drainBackgroundSilently();
            $this->closeResources();
            throw self::asTimeoutIfApplicable($firstError);
        }

        $this->cancel(null);
        $this->drainBackgroundSilently();

        $result = $this->materialize($bodyResult);
        $this->closeResources();
        return $result;
    }

    private function closeResources(): void
    {
        $this->sleepWrappers();
        $this->cleanupTempPaths();
    }

    private function sleepWrappers(): void
    {
        foreach ($this->wrappers as $wrapper) {
            $wrapper->sleepAll();
        }
        $this->wrappers = [];
    }

    private function cleanupTempPaths(): void
    {
        foreach ($this->tempPaths as $path) {
            try {
                if (!\Amp\File\exists($path)) {
                    continue;
                }
                if (\Amp\File\isDirectory($path)) {
                    \Aol\File::rmdir($path, recursive: true);
                } else {
                    \Amp\File\deleteFile($path);
                }
            } catch (\Throwable) {
            }
        }
        $this->tempPaths = [];
    }

    private static function asTimeoutIfApplicable(\Throwable $e): \Throwable
    {
        if (
            $e instanceof CancelledException
            && $e->getPrevious() instanceof TimeoutException
        ) {
            return new AolTimeoutException(
                'Scope exceeded its timeout.',
                previous: $e->getPrevious(),
            );
        }
        return $e;
    }

    private function drainAll(): ?\Throwable
    {
        $firstError = null;

        while (\count($this->pendings) > 0) {
            $futures = [];
            foreach ($this->pendings as $id => $p) {
                $futures[$id] = $p->internalFuture();
            }
            $this->pendings = [];

            foreach (Future::iterate($futures) as $future) {
                try {
                    $future->await();
                } catch (\Throwable $e) {
                    if ($firstError === null && !$this->isCancellationEcho($e)) {
                        $firstError = $e;
                        $this->cancel($e);
                    }
                }
            }
        }

        return $firstError;
    }

    private function drainSilently(): void
    {
        while (\count($this->pendings) > 0) {
            $futures = [];
            foreach ($this->pendings as $id => $p) {
                $futures[$id] = $p->internalFuture();
            }
            $this->pendings = [];

            foreach (Future::iterate($futures) as $future) {
                try {
                    $future->await();
                } catch (\Throwable) {
                }
            }
        }
    }

    private function drainBackgroundSilently(): void
    {
        while (\count($this->backgroundPendings) > 0) {
            $futures = [];
            foreach ($this->backgroundPendings as $id => $p) {
                $futures[$id] = $p->internalFuture();
            }
            $this->backgroundPendings = [];

            foreach (Future::iterate($futures) as $future) {
                try {
                    $future->await();
                } catch (\Throwable) {
                }
            }
        }
    }

    private function isCancellationEcho(\Throwable $e): bool
    {
        return $e instanceof CancelledException && $this->cancelled;
    }

    private function materialize(mixed $value): mixed
    {
        if ($value instanceof Pending) {
            return $value->internalFuture()->await();
        }
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $v instanceof Pending ? $v->internalFuture()->await() : $v;
            }
            return $out;
        }
        return $value;
    }
}
