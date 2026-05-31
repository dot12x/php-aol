<?php

declare(strict_types=1);

namespace Aol;

use Aol\File\Concerns\HandlesDir;
use Aol\File\Concerns\HandlesFormat;
use Aol\File\Concerns\HandlesFs;
use Aol\File\Concerns\HandlesIo;
use Aol\File\Concerns\HandlesLinks;
use Aol\File\Concerns\HandlesLock;
use Aol\File\Concerns\HandlesPerms;
use Aol\File\Concerns\HandlesRandom;
use Aol\File\Concerns\HandlesTemp;
use Aol\File\Concerns\HandlesWatch;

/**
 * Async filesystem facade. Wraps amphp/file.
 *
 * The facade itself is intentionally tiny — every category of method
 * lives in its own trait under src/File/Concerns/. Pull them in here
 * and the static surface is composed.
 *
 *   Io       — read, write, writeAtomic, append
 *   Format   — readJson, writeJson, readLines, stream
 *   Fs       — delete, move, rename, copy, exists, stat
 *   Dir      — mkdir, rmdir, list, walk
 *   Perms    — chmod, chown, touch
 *   Links    — symlink, readlink, hardlink
 *   Random   — open (returns Handle)
 *   Temp     — temp, tempDir, keepTemp (scope auto-cleanup)
 *   Lock     — withLock (contextual exclusive/shared)
 *   Watch    — watch (poll-based generator of FileEvent)
 *
 * Inside an Aol::scope every operation cooperatively yields to the
 * event loop. Outside it still works, just without cancellation.
 */
final class File
{
    use HandlesIo;
    use HandlesFormat;
    use HandlesFs;
    use HandlesDir;
    use HandlesPerms;
    use HandlesLinks;
    use HandlesRandom;
    use HandlesTemp;
    use HandlesLock;
    use HandlesWatch;
}
