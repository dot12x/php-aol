<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\Stat;
use function Amp\File\deleteFile;
use function Amp\File\exists as ampExists;
use function Amp\File\getStatus;
use function Amp\File\move;
use function Amp\File\read as ampRead;
use function Amp\File\write as ampWrite;

/**
 * @internal Used only by Aol\File. Covers delete/move/copy/exists/stat.
 */
trait HandlesFs
{
    public static function delete(string $path): void
    {
        deleteFile($path);
    }

    public static function move(string $from, string $to): void
    {
        move($from, $to);
    }

    public static function rename(string $from, string $to): void
    {
        move($from, $to);
    }

    public static function copy(string $from, string $to): void
    {
        ampWrite($to, ampRead($from));
    }

    public static function exists(string $path): bool
    {
        return ampExists($path);
    }

    public static function stat(string $path): ?Stat
    {
        $raw = getStatus($path);
        return $raw === null ? null : Stat::fromAmp($path, $raw);
    }
}
