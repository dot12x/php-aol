<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use function Amp\File\changeOwner;
use function Amp\File\changePermissions;
use function Amp\File\touch as ampTouch;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesPerms
{
    public static function chmod(string $path, int $mode): void
    {
        changePermissions($path, $mode);
    }

    public static function chown(string $path, ?int $uid = null, ?int $gid = null): void
    {
        changeOwner($path, $uid, $gid);
    }

    public static function touch(string $path, ?int $modTime = null, ?int $accessTime = null): void
    {
        ampTouch($path, $modTime, $accessTime);
    }
}
