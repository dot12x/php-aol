<?php

declare(strict_types=1);

namespace Aol\File;

use Aol\Support\Cast;

/**
 * Filesystem entry metadata. Returned by File::stat().
 */
final readonly class Stat
{
    private const S_IFMT = 0170000;
    private const S_IFREG = 0100000;
    private const S_IFDIR = 0040000;
    private const S_IFLNK = 0120000;

    public bool $isFile;
    public bool $isDir;
    public bool $isSymlink;

    public function __construct(
        public string $path,
        public int $size,
        public int $mode,
        public int $mtime,
        public int $atime,
        public int $ctime,
    ) {
        $type = $mode & self::S_IFMT;
        $this->isFile = $type === self::S_IFREG;
        $this->isDir = $type === self::S_IFDIR;
        $this->isSymlink = $type === self::S_IFLNK;
    }

    /**
     * @param array<string, mixed> $raw Result from Amp\File\getStatus()
     */
    public static function fromAmp(string $path, array $raw): self
    {
        return new self(
            path: $path,
            size:  Cast::pick($raw, 'size')->defaultValue(0)->toInt(),
            mode:  Cast::pick($raw, 'mode')->defaultValue(0)->toInt(),
            mtime: Cast::pick($raw, 'mtime')->defaultValue(0)->toInt(),
            atime: Cast::pick($raw, 'atime')->defaultValue(0)->toInt(),
            ctime: Cast::pick($raw, 'ctime')->defaultValue(0)->toInt(),
        );
    }
}
