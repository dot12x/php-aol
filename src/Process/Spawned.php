<?php

declare(strict_types=1);

namespace Aol\Process;

use Amp\Process\Process as AmpProcess;

/**
 * Long-lived child process. Use stdout/stderr generators for line-by-
 * line streaming; kill() to send SIGTERM; wait() for exit code.
 */
final class Spawned
{
    public function __construct(public readonly AmpProcess $process)
    {
    }

    public function pid(): int
    {
        return $this->process->getPid();
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    /**
     * @return \Generator<int, string>
     */
    public function stdout(): \Generator
    {
        return $this->lines($this->process->getStdout());
    }

    /**
     * @return \Generator<int, string>
     */
    public function stderr(): \Generator
    {
        return $this->lines($this->process->getStderr());
    }

    public function writeStdin(string $data): void
    {
        $this->process->getStdin()->write($data);
    }

    public function closeStdin(): void
    {
        $this->process->getStdin()->end();
    }

    public function kill(int $signo = \SIGTERM): void
    {
        if ($this->process->isRunning()) {
            $this->process->signal($signo);
        }
    }

    public function wait(): int
    {
        return $this->process->join();
    }

    /**
     * @return \Generator<int, string>
     */
    private function lines(\Amp\ByteStream\ReadableStream $stream): \Generator
    {
        $i = 0;
        $buffer = '';
        while (($chunk = $stream->read()) !== null) {
            $buffer .= $chunk;
            while (($pos = \strpos($buffer, "\n")) !== false) {
                yield $i++ => \substr($buffer, 0, $pos);
                $buffer = \substr($buffer, $pos + 1);
            }
        }
        if ($buffer !== '') {
            yield $i => $buffer;
        }
    }
}
