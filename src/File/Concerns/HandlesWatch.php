<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\FileEvent;
use Aol\File\FileEventType;
use Aol\Internal\ScopeStack;
use function Amp\File\listFiles;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesWatch
{
    /**
     * Poll-based filesystem watcher. Yields FileEvent objects when
     * files appear, change, or disappear. Stops cleanly when the
     * surrounding scope is cancelled.
     *
     * @return \Generator<int, FileEvent>
     */
    public static function watch(string $path, float $interval = 1.0): \Generator
    {
        $scope = ScopeStack::current();
        $snapshot = self::watchSnapshot($path);
        $i = 0;

        while (true) {
            if ($scope !== null) {
                if ($scope->cancellation()->isRequested()) {
                    return;
                }
                $scope->clock()->sleep($interval, $scope->cancellation());
            } else {
                \Amp\delay($interval);
            }

            $current = self::watchSnapshot($path);
            foreach (self::watchDiff($snapshot, $current) as $event) {
                yield $i++ => $event;
            }
            $snapshot = $current;
        }
    }

    /**
     * Build a snapshot of path => fingerprint using native stat and a
     * fast content hash. Using both mtime and a crc32 hash ensures that
     * changes are detected even on filesystems where mtime has only
     * one-second granularity (macOS APFS, FAT32, etc.) and even when
     * the file size does not change between writes.
     * clearstatcache() is called to bypass PHP's stat cache, which
     * does not automatically invalidate when another process modifies
     * the file without going through amphp/file's write functions.
     *
     * @return array<string, string> path => fingerprint
     */
    private static function watchSnapshot(string $path): array
    {
        \clearstatcache(true, $path);
        if (!\file_exists($path)) {
            return [];
        }
        if (\is_file($path)) {
            return [$path => self::watchFingerprint($path)];
        }

        $out = [];
        foreach (listFiles($path) as $name) {
            $sub = $path . '/' . $name;
            \clearstatcache(true, $sub);
            try {
                if (\is_file($sub)) {
                    $out[$sub] = self::watchFingerprint($sub);
                }
            } catch (\Throwable) {
            }
        }
        return $out;
    }

    /**
     * Combine mtime (seconds) and a fast crc32 content hash into a
     * single string fingerprint. The hash catches same-second content
     * changes that mtime alone would miss.
     */
    private static function watchFingerprint(string $path): string
    {
        $mtime = \filemtime($path);
        $hash = \hash_file('crc32b', $path);
        return ($mtime !== false ? (string) $mtime : '0') . ':' . ($hash !== false ? $hash : '');
    }

    /**
     * @param array<string, string> $before  path => fingerprint
     * @param array<string, string> $after   path => fingerprint
     * @return list<FileEvent>
     */
    private static function watchDiff(array $before, array $after): array
    {
        $now = \microtime(true);
        $events = [];

        foreach ($after as $path => $fingerprint) {
            if (!isset($before[$path])) {
                $events[] = new FileEvent(path: $path, type: FileEventType::Created, at: $now);
            } elseif ($before[$path] !== $fingerprint) {
                $events[] = new FileEvent(path: $path, type: FileEventType::Modified, at: $now);
            }
        }
        foreach ($before as $path => $_) {
            if (!isset($after[$path])) {
                $events[] = new FileEvent(path: $path, type: FileEventType::Deleted, at: $now);
            }
        }
        return $events;
    }
}
