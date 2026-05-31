<?php

declare(strict_types=1);

namespace Aol\File;

/**
 * A single directory entry, yielded by File::list() and File::walk().
 *
 * Stat is lazily fetched on first access — listing a huge directory
 * doesn't pay for stat() per entry unless the consumer asks.
 */
final class DirEntry
{
    private ?Stat $stat = null;
    private bool $statResolved = false;

    public function __construct(
        public readonly string $name,
        public readonly string $path,
    ) {
    }

    public function stat(): ?Stat
    {
        if (!$this->statResolved) {
            $this->stat = \Aol\File::stat($this->path);
            $this->statResolved = true;
        }
        return $this->stat;
    }

    public function isFile(): bool
    {
        $s = $this->stat();
        return $s !== null && $s->isFile;
    }

    public function isDir(): bool
    {
        $s = $this->stat();
        return $s !== null && $s->isDir;
    }

    public function isSymlink(): bool
    {
        $s = $this->stat();
        return $s !== null && $s->isSymlink;
    }
}
