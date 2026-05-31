# Attributes Reference

All attributes live under `Aol\Attribute\` unless otherwise stated.

## Contents

- [Concurrency — method level](#concurrency--method-level)
- [Concurrency — class level](#concurrency--class-level)
- [Lifecycle — method level](#lifecycle--method-level)
- [HTTP attributes](#http-attributes)
- [File attributes](#file-attributes)
- [Process attributes](#process-attributes)
- [Cross-cutting notes](#cross-cutting-notes)

---

## Concurrency — method level

### `#[Async]`

Marks a method as async. When called via a wrapped instance, returns `Pending<ReturnType>` instead of the real value. The call is scheduled inside the active scope.

Methods without `#[Async]` on a wrapped instance run synchronously and return the real value. The caller is responsible for not blocking the event loop in sync methods.

```php
<?php

use Aol\Attribute\Async;

class DataService
{
    #[Async]
    public function fetch(string $url): string { /* ... */ }

    public function baseUrl(): string { return 'https://api.example.com'; } // sync, fine
}
```

---

### `#[Timeout(int|float $seconds)]`

Per-method deadline. If the method does not complete within the duration, it is cancelled and `AolTimeoutException` is thrown.

```php
<?php

use Aol\Attribute\{Async, Timeout};

class ApiClient
{
    #[Async]
    #[Timeout(30)]
    public function fetch(string $url): string { /* ... */ }

    #[Async]
    #[Timeout(0.5)]
    public function ping(): bool { /* ... */ }
}
```

---

### `#[Retry(times:, on:, backoff:, delay:, maxDelay:)]`

Retry on exception. Runs before surfacing the failure to the scope.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `times` | `int` | — | Additional attempts (`times: 3` = up to 4 total calls) |
| `on` | `array<class-string<Throwable>>` | all exceptions | Retry only when thrown exception matches |
| `backoff` | `'fixed'\|'linear'\|'exponential'` | `'fixed'` | Delay growth strategy |
| `delay` | `int\|float` | `0` | Base delay in seconds |
| `maxDelay` | `int\|float` | no cap | Cap for backoff delay |

```php
<?php

use Aol\Attribute\{Async, Retry, Timeout};

class HttpService
{
    #[Async]
    #[Timeout(30)]
    #[Retry(
        times: 3,
        on: [NetworkException::class, TimeoutException::class],
        backoff: 'exponential',
        delay: 0.1,
        maxDelay: 5,
    )]
    public function fetch(string $url): string { /* ... */ }
}
```

---

## Concurrency — class level

### `#[Worker(pool: int, queue: int)]`

Declares a class is animated as a pool of N instances. Calls are distributed across instances. `queue` sets the maximum number of pending calls waiting for a free instance (backpressure).

| Parameter | Type | Default |
|---|---|---|
| `pool` | `int` | `1` |
| `queue` | `int` | `1024` |

```php
<?php

use Aol\Attribute\Worker;

#[Worker(pool: 4, queue: 1024)]
class JobProcessor { /* ... */ }
```

Without `#[Worker]`, an implicit `pool: 1, queue: 1024` applies.

---

### `#[Restart(max: int, within: int|float)]`

If a worker instance crashes, AOL restarts it (one-for-one — only the crashed instance, not the whole pool).

| Parameter | Type | Default |
|---|---|---|
| `max` | `int` | `5` |
| `within` | `int\|float` | `60` |

```php
<?php

use Aol\Attribute\{Worker, Restart};

#[Worker(pool: 4)]
#[Restart(max: 5, within: 60)]
class JobProcessor { /* ... */ }
```

Without `#[Restart]`, crashed instances are not replaced — the pool shrinks.

---

## Lifecycle — method level

### `#[OnAwake]`

Called once per instance right after `Aol::wrap()` creates it. Use for setup: opening connections, loading caches.

### `#[OnSleep]`

Called once per instance when its owning scope closes. Use for graceful cleanup.

```php
<?php

use Aol\Attribute\{Async, Worker, OnAwake, OnSleep};

#[Worker(pool: 2)]
class DbWorker
{
    private \PDO $db;

    #[OnAwake]
    public function connect(): void
    {
        $this->db = new \PDO('mysql:host=localhost;dbname=app', 'user', 'pass');
    }

    #[OnSleep]
    public function disconnect(): void
    {
        unset($this->db);
    }

    #[Async]
    public function query(string $sql): array { /* ... */ }
}
```

---

### `#[OnSignal(int $signal)]`

Called when the given UNIX signal arrives. Use PHP's `SIG*` constants.

```php
<?php

use Aol\Attribute\OnSignal;

class Daemon
{
    #[OnSignal(SIGTERM)]
    public function shutdown(): void { /* graceful stop */ }

    #[OnSignal(SIGHUP)]
    public function reload(): void { /* reload config */ }
}
```

Requires `ext-pcntl`. Without it, signals are silently no-op.

---

### `#[OnTick(every: int|float)]`

Called periodically every N seconds while the scope is alive.

```php
<?php

use Aol\Attribute\OnTick;

class Monitor
{
    #[OnTick(every: 30)]
    public function healthCheck(): void { /* ... */ }
}
```

---

## HTTP attributes

Namespace: `Aol\Http\Attribute\`. Applied to an interface, not a class. See [http.md](./http.md) for full usage.

### Interface level

| Attribute | Description |
|---|---|
| `#[BaseUrl(string $url)]` | URL prefix applied to every method path |
| `#[Headers(array $headers)]` | Default headers sent with every request |

### Method level

| Attribute | Description |
|---|---|
| `#[Get(string $path)]` | HTTP GET, path may contain `{name}` placeholders |
| `#[Post(string $path)]` | HTTP POST |
| `#[Put(string $path)]` | HTTP PUT |
| `#[Patch(string $path)]` | HTTP PATCH |
| `#[Delete(string $path)]` | HTTP DELETE |
| `#[Header(string $name, string $value)]` | Static header on this specific call |

### Parameter level

| Attribute | Description |
|---|---|
| `#[Path]` | Fills a `{name}` placeholder in the URL |
| `#[Query]` | Added to the query string |
| `#[Body]` | Request body (JSON-encoded by default) |
| `#[Header(string $name)]` | Header value comes from this parameter |

---

## File attributes

Namespace: `Aol\File\Attribute\`.

### `#[OnFileChange(string $path, bool $recursive = false, array $events = [])]`

Method is called when a watched path changes.

`events` filters which events trigger the method. Empty array means all events: `Created`, `Modified`, `Deleted`, `Renamed`.

> **Note (v0.1.0):** The `recursive` flag is accepted but is currently a no-op. Recursive directory watching will be added in a later release. Non-recursive watching works as documented.

```php
<?php

use Aol\File\Attribute\OnFileChange;
use Aol\File\FileEvent;

class ConfigWatcher
{
    #[OnFileChange('/etc/app.json')]
    public function reload(FileEvent $e): void
    {
        // reload config
    }

    #[OnFileChange('/plugins/', recursive: true, events: ['Created', 'Deleted'])]
    public function pluginsChanged(FileEvent $e): void
    {
        // note: recursive is a no-op in v0.1.0
    }
}
```

---

## Process attributes

Namespace: `Aol\Process\Attribute\`.

| Attribute | Target | Description |
|---|---|---|
| `#[Process(string $command, bool $restart = false)]` | class | Spawns child at `Aol::wrap()` time; `restart: true` respawns on exit |
| `#[OnStdout]` | method | Called per line of child stdout; signature `(string $line): void` |
| `#[OnStderr]` | method | Called per line of child stderr; signature `(string $line): void` |
| `#[OnExit]` | method | Called once when child exits; signature `(int $code): void` |

See [process.md](./process.md) for a full daemon example.

---

## SSE attributes

Namespace: `Aol\Http\Attribute\` (SSE rides on HTTP).

| Attribute | Target | Description |
|---|---|---|
| `#[SseStream]` | method (HTTP interface) | Marks an interface method as a Server-Sent Events stream; the proxy returns `iterable<int, SseEvent>` over the live response body. |
| `#[OnSse(string $url)]` | method (wrapped class), repeatable | Subscribes the method to an SSE stream; signature `(SseEvent $event): void`. |

See [http.md](./http.md#server-sent-events-sse) for full examples.

---

## WebSocket attributes

Namespace: `Aol\WebSocket\Attribute\`.

| Attribute | Target | Description |
|---|---|---|
| `#[WebSocket(string $url)]` | class | Animates the class as a WebSocket client; one connection per pool instance |
| `#[WsConnection]` | property | Wrapper hydrates the property with the live `Aol\WebSocket\Connection` before `#[OnOpen]` |
| `#[OnOpen]` | method | `(): void` — runs synchronously after connect |
| `#[OnMessage]` | method | `(Aol\WebSocket\Message $m): void` — runs per received frame |
| `#[OnClose]` | method | `(?int $code, ?string $reason): void` — runs once when the receive loop ends |

See [websocket.md](./websocket.md) for the full example.

---

## Cross-cutting notes

- `#[Timeout]` and `#[Retry]` can be combined on the same method. Retry runs first; if all retries are exhausted the timeout is still enforced on the overall method call.
- `#[Async]` is required for `#[Timeout]` and `#[Retry]` to have effect — they are no-ops on sync methods.
- `#[Restart]` is only meaningful on classes that also have `#[Worker]`.
- `#[OnSignal]`, `#[OnTick]`, `#[OnFileChange]`, `#[OnSse]`, and the WebSocket lifecycle attributes are wired by the Wrapper at `Aol::wrap()` time and fire as `asyncBackground` work — they do not block scope close.
