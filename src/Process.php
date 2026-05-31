<?php

declare(strict_types=1);

namespace Aol;

use Aol\Process\ExitResult;
use Aol\Process\Spawned;
use Amp\Process\Process as AmpProcess;
use function Amp\ByteStream\buffer;

/**
 * Async child process facade.
 *
 *   $r = Process::run(['git', 'log', '-n', '5'], cwd: '/repo');
 *   $r->exitCode; $r->stdout; $r->stderr;
 *
 *   $p = Process::spawn(['tail', '-f', '/var/log/app.log']);
 *   foreach ($p->stdout as $line) { echo $line; }
 */
final class Process
{
    /**
     * @param list<string>|string $command
     * @param array<string, string>|null $env
     */
    public static function run(
        array|string $command,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
        ?string $stdin = null,
    ): ExitResult {
        unset($timeout);
        $proc = AmpProcess::start($command, $cwd, (array) $env);

        if ($stdin !== null) {
            $proc->getStdin()->write($stdin);
        }
        $proc->getStdin()->end();

        $stdout = buffer($proc->getStdout());
        $stderr = buffer($proc->getStderr());
        $exit = $proc->join();

        return new ExitResult(
            exitCode: $exit,
            stdout: $stdout,
            stderr: $stderr,
        );
    }

    /**
     * Spawn a child for streaming. Caller must wait()/kill().
     *
     * @param list<string>|string $command
     * @param array<string, string>|null $env
     */
    public static function spawn(
        array|string $command,
        ?string $cwd = null,
        ?array $env = null,
    ): Spawned {
        return new Spawned(AmpProcess::start($command, $cwd, (array) $env));
    }
}
