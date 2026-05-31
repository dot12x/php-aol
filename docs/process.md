# Process

`Aol\Process` manages child processes. Three modes: one-shot (imperative), streaming (imperative), and declarative long-running daemon.

Underlying transport: `amphp/process`.

---

## One-shot

Run a command, wait for it to finish, get the result.

```php
<?php

use Aol\Process;

$result = Process::run(
    ['git', 'log', '--oneline', '-n', '10'],
    cwd: '/repo',
    timeout: 30,
);

echo $result->exitCode;   // int
echo $result->stdout;     // string
echo $result->stderr;     // string

if ($result->ok()) {
    echo "success\n";
}
```

### `ExitResult`

| Member | Type | Description |
|---|---|---|
| `exitCode` | `int` | Process exit code |
| `stdout` | `string` | Full stdout output |
| `stderr` | `string` | Full stderr output |
| `ok()` | `bool` | `true` when `exitCode === 0` |

### Signature

```php
Process::run(
    array $command,
    string $cwd = '.',
    array $env = [],
    int|float $timeout = 60,
    string $stdin = '',
): ExitResult
```

---

## Streaming

Start a process and consume its output line by line without waiting for it to finish.

```php
<?php

use Aol\Process;

$p = Process::spawn(['tail', '-f', '/var/log/app.log']);

echo "PID: " . $p->pid() . "\n";

foreach ($p->stdout() as $line) {
    echo "out: $line\n";
}

foreach ($p->stderr() as $line) {
    echo "err: $line\n";
}
```

### `Spawned`

| Member | Description |
|---|---|
| `pid()` | Child process PID |
| `isRunning()` | Whether the process is still alive |
| `stdout()` | Generator of stdout lines |
| `stderr()` | Generator of stderr lines |
| `writeStdin(string $data)` | Write to child's stdin |
| `closeStdin()` | Close stdin (signals EOF to child) |
| `kill(int $signal = SIGTERM)` | Send a signal |
| `wait()` | Block until process exits, returns exit code |

---

## Declarative daemon

For long-running processes that should be supervised, use `#[Process]` with `#[OnStdout]`, `#[OnStderr]`, and `#[OnExit]`. The child is spawned at `Aol::wrap()` time, one per pool instance, and killed (SIGTERM) when the scope closes.

```php
<?php

use Aol\Aol;
use Aol\Time;
use Aol\Process\Attribute\Process as ProcessAttr;
use Aol\Process\Attribute\{OnStdout, OnStderr, OnExit};

#[ProcessAttr('redis-server /etc/redis/redis.conf', restart: true)]
class RedisDaemon
{
    #[OnStdout]
    public function log(string $line): void
    {
        error_log('[redis] ' . $line);
    }

    #[OnStderr]
    public function err(string $line): void
    {
        error_log('[redis:err] ' . $line);
    }

    #[OnExit]
    public function exited(int $code): void
    {
        error_log('[redis] exited with code ' . $code);
    }
}

Aol::scope(function () {
    $redis = Aol::wrap(RedisDaemon::class);
    Time::sleep(PHP_INT_MAX);   // park until cancelled
});
// redis-server is killed here
```

`restart: true` respawns the child automatically when it exits, unless the surrounding scope is being cancelled.

One child process is created per pool instance. If you use `#[Worker(pool: 3)]` with `#[ProcessAttr]`, three child processes are started.

See [attributes.md](./attributes.md#process-attributes) for the full attribute reference.
