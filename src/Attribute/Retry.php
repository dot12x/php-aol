<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Retry an async method when it throws. `times` is the number of
 * additional retries — so total attempts = 1 + times.
 *
 * - $on: only retry if the thrown exception is an instance of one of
 *   these classes. Default: any \Throwable. Pass an empty array to
 *   retry on nothing (effectively disables retry).
 * - $backoff: 'fixed' | 'linear' | 'exponential'.
 *   - fixed: delay seconds every time
 *   - linear: delay × attempt
 *   - exponential: delay × 2^(attempt-1)
 * - $delay: base delay in seconds. 0 = no wait between retries.
 * - $maxDelay: cap for backoff in seconds. null = no cap.
 *
 * If the scope is cancelled (sibling crash, scope timeout) the retry
 * loop bails immediately with the cancellation exception.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Retry
{
    /**
     * @param list<class-string<\Throwable>> $on
     */
    public function __construct(
        public int $times,
        public array $on = [\Throwable::class],
        public string $backoff = 'fixed',
        public int|float $delay = 0,
        public int|float|null $maxDelay = null,
    ) {
        if ($times < 0) {
            throw new \InvalidArgumentException('Retry times must be ≥ 0.');
        }
        if (!\in_array($backoff, ['fixed', 'linear', 'exponential'], true)) {
            throw new \InvalidArgumentException("Unknown backoff strategy '{$backoff}'.");
        }
        if ($delay < 0) {
            throw new \InvalidArgumentException('Retry delay must be ≥ 0.');
        }
        if ($maxDelay !== null && $maxDelay < 0) {
            throw new \InvalidArgumentException('Retry maxDelay must be ≥ 0.');
        }
    }

    public function shouldRetry(\Throwable $e): bool
    {
        foreach ($this->on as $class) {
            if ($e instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delay in seconds before attempt N (where N=2 is the first retry).
     */
    public function delayFor(int $attempt): float
    {
        $base = match ($this->backoff) {
            'fixed' => (float) $this->delay,
            'linear' => (float) $this->delay * $attempt,
            'exponential' => (float) $this->delay * (2 ** ($attempt - 1)),
            default => throw new \LogicException("Unreachable: backoff '{$this->backoff}' validated in constructor."),
        };
        if ($this->maxDelay !== null) {
            return \min($base, (float) $this->maxDelay);
        }
        return $base;
    }
}
