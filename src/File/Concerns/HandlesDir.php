<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\DirEntry;
use function Amp\File\createDirectory;
use function Amp\File\createDirectoryRecursively;
use function Amp\File\deleteDirectory;
use function Amp\File\deleteFile;
use function Amp\File\isDirectory;
use function Amp\File\listFiles;

/**
 * @internal Used only by Aol\File. Covers mkdir/rmdir/list/walk.
 */
trait HandlesDir
{
    public static function mkdir(string $path, int $mode = 0777, bool $recursive = true): void
    {
        if ($recursive) {
            createDirectoryRecursively($path, $mode);
        } else {
            createDirectory($path, $mode);
        }
    }

    public static function rmdir(string $path, bool $recursive = false): void
    {
        if ($recursive) {
            self::rmdirRecursive($path);
            return;
        }
        deleteDirectory($path);
    }

    /**
     * Lazy generator of immediate children. Entries are not stat'd
     * upfront — DirEntry stats on demand.
     *
     * @return \Generator<int, DirEntry>
     */
    public static function list(string $path): \Generator
    {
        $i = 0;
        foreach (listFiles($path) as $name) {
            yield $i++ => new DirEntry(name: $name, path: $path . '/' . $name);
        }
    }

    /**
     * Recursive lazy walk (depth-first pre-order).
     *
     * @param callable(DirEntry): bool|null $filter        Return false to skip an entry.
     * @return \Generator<int, DirEntry>
     */
    public static function walk(
        string $path,
        ?int $maxDepth = null,
        ?callable $filter = null,
        bool $followSymlinks = false,
    ): \Generator {
        yield from self::walkInner($path, depth: 0, maxDepth: $maxDepth, filter: $filter, followSymlinks: $followSymlinks, counter: new \stdClass());
    }

    /**
     * @return \Generator<int, DirEntry>
     */
    private static function walkInner(
        string $path,
        int $depth,
        ?int $maxDepth,
        ?callable $filter,
        bool $followSymlinks,
        \stdClass $counter,
    ): \Generator {
        if (!isset($counter->i)) {
            $counter->i = 0;
        }
        foreach (listFiles($path) as $name) {
            $entry = new DirEntry(name: $name, path: $path . '/' . $name);
            if ($filter !== null && $filter($entry) === false) {
                continue;
            }
            yield $counter->i++ => $entry;
            if (!$entry->isDir()) {
                continue;
            }
            if ($entry->isSymlink() && !$followSymlinks) {
                continue;
            }
            if ($maxDepth !== null && $depth + 1 >= $maxDepth) {
                continue;
            }
            yield from self::walkInner($entry->path, $depth + 1, $maxDepth, $filter, $followSymlinks, $counter);
        }
    }

    private static function rmdirRecursive(string $path): void
    {
        foreach (listFiles($path) as $entry) {
            $sub = $path . '/' . $entry;
            if (isDirectory($sub)) {
                self::rmdirRecursive($sub);
            } else {
                deleteFile($sub);
            }
        }
        deleteDirectory($path);
    }
}
