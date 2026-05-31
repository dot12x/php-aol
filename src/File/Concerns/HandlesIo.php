<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use function Amp\File\move;
use function Amp\File\openFile;
use function Amp\File\read as ampRead;
use function Amp\File\write as ampWrite;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesIo
{
    public static function read(string $path): string
    {
        return ampRead($path);
    }

    /**
     * Write $data to $path. With atomic: true, writes to a temp file
     * and renames into place.
     */
    public static function write(string $path, string $data, bool $atomic = false): void
    {
        if ($atomic) {
            self::writeAtomic($path, $data);
            return;
        }
        ampWrite($path, $data);
    }

    public static function writeAtomic(string $path, string $data): void
    {
        $tmp = $path . '.tmp.' . \bin2hex(\random_bytes(8));
        ampWrite($tmp, $data);
        move($tmp, $path);
    }

    public static function append(string $path, string $data): void
    {
        $h = openFile($path, 'a');
        try {
            $h->write($data);
        } finally {
            $h->close();
        }
    }
}
