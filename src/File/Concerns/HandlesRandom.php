<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\Handle;
use function Amp\File\openFile;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesRandom
{
    /**
     * Open a file for random-access read/write. Returns a Handle —
     * remember to close() it (try/finally).
     *
     * Modes follow fopen() semantics: 'r', 'r+', 'w', 'w+', 'a', 'a+', ...
     */
    public static function open(string $path, string $mode): Handle
    {
        return new Handle(openFile($path, $mode));
    }
}
