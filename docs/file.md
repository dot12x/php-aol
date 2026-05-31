# File

`Aol\File` is a static facade for async filesystem operations. It is composed of trait concerns from `src/File/Concerns/Handles*.php` — the facade class itself contains no I/O implementation.

Underlying transport: `amphp/file`. For best performance, install `ext-eio` or `ext-uv` (amphp/file uses them automatically for native async file I/O; without them it falls back to a thread-pool approach that is still non-blocking).

---

## I/O

```php
<?php

use Aol\File;

$data = File::read('/etc/hosts');

File::write('/tmp/out.txt', $data);
File::write('/etc/config.json', $json, atomic: true);  // write to temp, then rename

File::append('/var/log/app.log', "new line\n");

foreach (File::stream('/large.csv', chunkSize: 8192) as $chunk) {
    process($chunk);
}
```

---

## Format helpers

```php
<?php

use Aol\File;

$config = File::readJson('/etc/app.json');   // array

foreach (File::readLines('/var/log/app.log') as $line) {
    echo $line . "\n";
}
```

---

## Filesystem operations

```php
<?php

use Aol\File;

File::exists('/etc/hosts');       // bool
File::delete('/tmp/stale.tmp');
File::copy('/src/a.txt', '/dst/a.txt');
File::move('/src/a.txt', '/dst/a.txt');
File::rename('/old.txt', '/new.txt');
File::touch('/tmp/marker');

$stat = File::stat('/etc/hosts');
// $stat->size, $stat->mtime, $stat->mode, $stat->isFile, $stat->isDir
```

---

## Directory operations

```php
<?php

use Aol\File;

File::mkdir('/var/app/cache');
File::rmdir('/var/app/cache');

foreach (File::list('/var/log') as $entry) {
    echo $entry->name . "\n";   // DirEntry
}

// walk() is a lazy generator — entries are yielded as discovered
foreach (File::walk('/src', maxDepth: 3, filter: fn($e) => $e->isFile) as $entry) {
    echo $entry->path . "\n";
}
```

---

## Permissions and links

```php
<?php

use Aol\File;

File::chmod('/tmp/script.sh', 0o755);
File::chown('/tmp/file.txt', user: 'www-data');

File::symlink('/etc/nginx/sites-available/app', '/etc/nginx/sites-enabled/app');
$target = File::readlink('/etc/nginx/sites-enabled/app');
File::hardlink('/etc/hosts', '/tmp/hosts-copy');
```

---

## Random access — Handle

`File::open()` returns a `Handle` for seek-based access.

```php
<?php

use Aol\File;

$f = File::open('/data/records.bin', 'r');
$f->seek(1024);
$chunk = $f->read(4096);
$f->close();

$f = File::open('/tmp/output.bin', 'w');
$f->write($header);
$f->write($payload);
$f->sync();    // flush to OS
$f->close();
```

| Method | Description |
|---|---|
| `read(int $length)` | Read up to N bytes |
| `write(string $data)` | Write bytes at current position |
| `seek(int $offset)` | Move file pointer |
| `tell()` | Current position |
| `truncate(int $size = 0)` | Truncate file |
| `sync()` | Flush to OS |
| `close()` | Close the handle |

---

## Temp files and directories

Temp files and directories are scope-owned and deleted automatically when the scope closes.

```php
<?php

use Aol\Aol;
use Aol\File;

$result = Aol::scope(function () {
    $tmp = File::temp();          // Handle, writable temp file
    File::write($tmp->path(), $largeData);

    $dir = File::tempDir();       // string path to temp directory
    File::write($dir . '/chunk.bin', $data);

    return processTemp($tmp, $dir);
});
// temp file and temp dir deleted here
```

---

## Lock

`File::withLock()` provides cooperative file locking using amphp/file advisory locks.

```php
<?php

use Aol\File;

$count = File::withLock(
    '/var/counter.json',
    function ($f) {
        $data = json_decode($f->readAll(), true);
        $data['n']++;
        $f->seek(0);
        $f->truncate();
        $f->write(json_encode($data));
        return $data['n'];
    },
    exclusive: true,
);
```

---

## Watch

`File::watch()` returns an iterator that yields `FileEvent` objects when the watched path changes. It is poll-based using native `filemtime` + `crc32b` fingerprinting, which handles same-second changes that `mtime` alone would miss.

```php
<?php

use Aol\File;
use Aol\File\FileEvent;

Aol::scope(function () {
    foreach (File::watch('/etc/app.json') as $event) {
        /** @var FileEvent $event */
        echo $event->path . ' changed' . "\n";
        reloadConfig();
    }
});
```

For declarative watching inside a wrapped class, use the [`#[OnFileChange]`](./attributes.md#onfilechange) attribute instead.

> **Note (v0.1.0):** `File::watch()` is not recursive. Watching a directory detects changes to files directly inside it, not in subdirectories. Recursive watching will be added in a later release.
