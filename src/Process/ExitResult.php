<?php

declare(strict_types=1);

namespace Aol\Process;

/**
 * Result of Process::run().
 */
final readonly class ExitResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function ok(): bool
    {
        return $this->exitCode === 0;
    }
}
