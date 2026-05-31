<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use Aol\File\Handle;
use Aol\Internal\ScopeStack;
use function Amp\File\createDirectoryRecursively;
use function Amp\File\openFile;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesTemp
{
    /**
     * Create a unique temporary file and return a write-mode Handle.
     * If called inside an Aol::scope, the file is deleted when the
     * scope closes.
     */
    public static function temp(string $prefix = 'aol-'): Handle
    {
        $path = \sys_get_temp_dir() . '/' . $prefix . \bin2hex(\random_bytes(8));
        $handle = openFile($path, 'w+');
        self::registerForScopeCleanup($path);
        return new Handle($handle);
    }

    /**
     * Create a unique temporary directory; return its path. If called
     * inside an Aol::scope, the directory (recursively) is removed
     * when the scope closes.
     */
    public static function tempDir(string $prefix = 'aol-'): string
    {
        $path = \sys_get_temp_dir() . '/' . $prefix . \bin2hex(\random_bytes(8));
        createDirectoryRecursively($path);
        self::registerForScopeCleanup($path);
        return $path;
    }

    /**
     * Detach a path from scope cleanup so it survives past scope close.
     * Optionally move it to a permanent location.
     */
    public static function keepTemp(string $path, ?string $newPath = null): string
    {
        ScopeStack::current()?->unregisterTempPath($path);
        if ($newPath !== null && $newPath !== $path) {
            self::move($path, $newPath);
            return $newPath;
        }
        return $path;
    }

    private static function registerForScopeCleanup(string $path): void
    {
        ScopeStack::current()?->registerTempPath($path);
    }
}
