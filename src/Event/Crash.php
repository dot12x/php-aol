<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Emitted when an async method's *final* attempt throws — i.e. after
 * any retries have been exhausted. The original exception is
 * preserved; this is purely a notification.
 */
final readonly class Crash extends AolEvent
{
    public function __construct(
        public string $className,
        public string $method,
        public int $instanceIndex,
        public \Throwable $error,
        float $at,
    ) {
        parent::__construct($at);
    }
}
