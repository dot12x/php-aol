<?php

declare(strict_types=1);

namespace Aol\Process\Attribute;

/**
 * Declares a class as the supervisor of a long-running child process.
 * Wrapper spawns one child per pool instance at Aol::wrap() time and
 * kills it (SIGTERM) at scope close. With restart: true, the child is
 * respawned each time it exits, until the scope cancels.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Process
{
    public function __construct(
        public string $command,
        public bool $restart = false,
    ) {
    }
}
