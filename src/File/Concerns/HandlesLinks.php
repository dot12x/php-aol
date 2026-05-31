<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use function Amp\File\createHardlink;
use function Amp\File\createSymlink;
use function Amp\File\resolveSymlink;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesLinks
{
    public static function symlink(string $target, string $link): void
    {
        createSymlink($target, $link);
    }

    public static function readlink(string $path): string
    {
        return resolveSymlink($path);
    }

    public static function hardlink(string $target, string $link): void
    {
        createHardlink($target, $link);
    }
}
