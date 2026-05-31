<?php

declare(strict_types=1);

namespace Aol\File;

use Amp\File\File as AmpFile;
use Amp\File\Whence;

/**
 * Random-access file handle. Returned by File::open().
 *
 * Wraps amphp/file's File interface. Always close handles with
 * close() (or use try/finally) — handles do not auto-close on
 * garbage collection in any deterministic timeframe.
 */
final class Handle
{
    public function __construct(private readonly AmpFile $impl)
    {
    }

    public function read(int $length = 8192): ?string
    {
        return $this->impl->read(length: $length);
    }

    public function readAll(): string
    {
        $out = '';
        while (!$this->impl->eof()) {
            $chunk = $this->impl->read();
            if ($chunk === null) {
                break;
            }
            $out .= $chunk;
        }
        return $out;
    }

    public function write(string $data): void
    {
        $this->impl->write($data);
    }

    public function seek(int $position, int $whence = SEEK_SET): int
    {
        $w = match ($whence) {
            SEEK_SET => Whence::Start,
            SEEK_CUR => Whence::Current,
            SEEK_END => Whence::End,
            default => throw new \InvalidArgumentException("Unknown whence: {$whence}"),
        };
        return $this->impl->seek($position, $w);
    }

    public function tell(): int
    {
        return $this->impl->tell();
    }

    public function eof(): bool
    {
        return $this->impl->eof();
    }

    public function truncate(int $size): void
    {
        $this->impl->truncate($size);
    }

    public function close(): void
    {
        if (!$this->impl->isClosed()) {
            $this->impl->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->impl->isClosed();
    }

    public function path(): string
    {
        return $this->impl->getPath();
    }

    /**
     * @internal Used by File::withLock and other helpers.
     */
    public function inner(): AmpFile
    {
        return $this->impl;
    }
}
