# php-aol

PHP 8.4+ attribute-driven async/concurrency library on Fibers + Revolt.

## What it is

A PHP async/concurrency library. The defining quality is attribute-driven declarative API: users do not write `await`, `yield`, `then()`, `go()`, `spawn`, or `task`. They write ordinary PHP classes annotated with attributes; AOL animates them at `Aol::wrap()` time and lets them live inside `Aol::scope()`. Process monitoring and concurrency share one mental model — wrap a class, let it live in a scope, let attributes describe its lifecycle and concurrency.

## Requirements

- PHP 8.4+
- Revolt event loop (`revolt/event-loop ^1.0`)

## Install

```bash
composer require php-aol/php-aol
```

## Five-minute tour

### 1. Wrap, scope, async, auto-graph

```php
use Aol\Aol;
use Aol\Attribute\Async;

class ImageProcessor
{
    #[Async]
    public function resize(string $path, int $width): Image { ... }

    #[Async]
    public function compress(Image $img): Image { ... }
}

$proc = Aol::wrap(ImageProcessor::class);

$result = Aol::scope(function () use ($proc) {
    $a = $proc->resize('a.jpg', 800);   // Pending<Image>
    $b = $proc->compress($a);           // depends on $a — runs after
    $c = $proc->resize('b.jpg', 800);   // parallel with $a
    return [$b, $c];                    // scope close → real Images
});
```

`Pending` values passed as arguments declare dependencies automatically. The scope resolves all pending work before returning.

### 2. Worker pool with lifecycle

```php
use Aol\Aol;
use Aol\Attribute\{Async, Worker, Restart, OnAwake, OnSleep, Retry, Timeout};

#[Worker(pool: 4, queue: 1024)]
#[Restart(max: 5, within: 60)]
class JobProcessor
{
    private \PDO $db;

    #[OnAwake]
    public function connect(): void { $this->db = new \PDO(...); }

    #[OnSleep]
    public function disconnect(): void { $this->db = null; }

    #[Async]
    #[Retry(times: 3, backoff: 'exponential', delay: 0.1, maxDelay: 5)]
    #[Timeout(30)]
    public function process(Job $job): Result { ... }
}

$pool = Aol::wrap(JobProcessor::class);  // 4 instances, OnAwake runs eagerly

Aol::scope(function () use ($pool, $jobs) {
    foreach ($jobs as $job) {
        $pool->process($job);            // distributed across 4 workers
    }
});                                      // OnSleep runs on each instance
```

### 3. Server-Sent Events (SSE)

```php
use Aol\Aol;
use Aol\Http;

Aol::scope(function () {
    foreach (Http::sse('https://api.example.com/events') as $event) {
        echo "[{$event->event}] {$event->data}\n";
    }
});
```

Or declaratively on a wrapped class:

```php
use Aol\Attribute\Worker;
use Aol\Http\Attribute\OnSse;
use Aol\Http\Sse\SseEvent;

#[Worker]
class Ingestor
{
    #[OnSse('https://api.example.com/events')]
    public function rx(SseEvent $event): void { /* per-event handler */ }
}
```

### 4. WebSocket

```php
use Aol\Aol;
use Aol\WebSocket\WebSocket;

Aol::scope(function () {
    $ws = WebSocket::connect('wss://example.com/socket');
    $ws->send('hello');
    foreach ($ws->messages() as $msg) {
        echo $msg->payload, "\n";
    }
    $ws->close();
});
```

Or declaratively — `#[OnOpen]`/`#[OnMessage]`/`#[OnClose]` on a wrapped class. See [docs/websocket.md](docs/websocket.md).

### 5. Declarative HTTP client

```php
use Aol\Http;
use Aol\Http\Attribute\{BaseUrl, Headers, Get, Post, Delete};
use Aol\Http\Attribute\{Path, Query, Body, Header};
use Aol\Attribute\{Retry, Timeout};

#[BaseUrl('https://api.github.com')]
#[Headers(['Accept' => 'application/vnd.github+json'])]
interface GitHubApi
{
    #[Get('/users/{login}')]
    #[Retry(times: 3, on: [NetworkException::class])]
    #[Timeout(10)]
    public function getUser(#[Path] string $login): User;

    #[Get('/users/{login}/repos')]
    public function listRepos(
        #[Path] string $login,
        #[Query] string $type = 'all',
        #[Query] int $per_page = 30,
    ): array;

    #[Post('/user/repos')]
    public function createRepo(
        #[Body] CreateRepoRequest $req,
        #[Header('Authorization')] string $token,
    ): Repo;

    #[Delete('/repos/{owner}/{repo}')]
    public function deleteRepo(
        #[Path] string $owner,
        #[Path] string $repo,
    ): void;
}

$gh   = Http::fromInterface(GitHubApi::class);
$user = Aol::scope(fn() => $gh->getUser('sattorbek'));
```

## Attribute index

| Attribute | Target | What it does |
|---|---|---|
| `#[Async]` | method | Returns `Pending` instead of blocking; scheduled inside scope |
| `#[Timeout(seconds)]` | method | Cancels and throws `AolTimeoutException` after N seconds |
| `#[Retry(times:, on:, backoff:, delay:, maxDelay:)]` | method | Retries on exception with fixed / linear / exponential backoff |
| `#[Worker(pool:, queue:)]` | class | Animates class as a pool of N instances |
| `#[Restart(max:, within:)]` | class | Restarts crashed instances (one-for-one) |
| `#[OnAwake]` | method | Called once per instance at `Aol::wrap()` time |
| `#[OnSleep]` | method | Called once per instance when scope closes |
| `#[OnSignal(SIG*)]` | method | Called when a UNIX signal arrives |
| `#[OnTick(every:)]` | method | Called every N seconds |
| `#[OnFileChange(path)]` | method | Called when a watched path changes |
| `#[BaseUrl]`, `#[Headers]` | interface | HTTP client base URL and default headers |
| `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]` | method | HTTP verb + path template |
| `#[Path]`, `#[Query]`, `#[Body]`, `#[Header]` | parameter | HTTP request parameter binding |
| `#[Process(command)]` | class | Spawns a child process at wrap time; `restart: true` respawns on exit |
| `#[OnStdout]`, `#[OnStderr]` | method | Called per line of child process output |
| `#[OnExit]` | method | Called when the child process exits |
| `#[SseStream]` | method (HTTP interface) | Method returns an `iterable<SseEvent>` instead of decoded JSON |
| `#[OnSse(url)]` | method (wrapped class) | Subscribes the method to an SSE stream (repeatable) |
| `#[WebSocket(url)]` | class | Animates class as a WebSocket client (one connection per pool instance) |
| `#[WsConnection]` | property | Wrapper hydrates the property with the live `Connection` before `#[OnOpen]` |
| `#[OnOpen]`, `#[OnMessage]`, `#[OnClose]` | method | WebSocket lifecycle hooks |

Full reference: `.claude/skills/php-aol/attributes-reference.md`

## Module map

| Namespace | What it does |
|---|---|
| `Aol\Aol` | Entry points: `wrap()`, `scope()`, `async()`, `asyncBackground()` |
| `Aol\Pending` | In-flight value proxy with magic chaining |
| `Aol\Http` | Declarative HTTP client + static facade (incl. Server-Sent Events) |
| `Aol\WebSocket` | WebSocket client (imperative + declarative) |
| `Aol\File` | Async filesystem (read/write/stream/walk/watch/lock/temp) |
| `Aol\Stream` | TCP/UDP/Unix/TLS sockets + framing |
| `Aol\Process` | Child processes (one-shot + spawn + declarative `#[Process]`) |
| `Aol\Time` | Async sleep / deadline / interval |
| `Aol\Sync` | Async-aware Mutex, Semaphore, Barrier, WaitGroup |
| `Aol\Queue` | In-memory Queue and Topic (pub/sub) |
| `Aol\Test` | `FakeClock`, `runScope` |

## PHPStan extension

The library ships a PHPStan extension at `ext/phpstan/`. If you have `phpstan/extension-installer` installed, it loads automatically. Otherwise, add this to your `phpstan.neon`:

```neon
includes:
    - vendor/php-aol/php-aol/ext/phpstan/extension.neon
```

## Runnable examples

The `examples/` directory has self-contained demos you can run directly:

| Example | What it shows |
|---|---|
| `examples/daemon.php` | Long-running daemon with `#[OnTick]` and `#[OnSignal]` |
| `examples/process.php` | Declarative `#[Process]` + `#[OnStdout]`/`#[OnExit]` |
| `examples/http-parallel.php` | 5 HTTP requests in parallel — typically 5× faster than sequential |
| `examples/http-declarative.php` | Retrofit-style HTTP client from a plain PHP interface |
| `examples/sse-imperative.php` | `Http::sse()` consuming a Server-Sent Events feed |
| `examples/sse-declarative.php` | `#[OnSse]` on a wrapped class — handler per event |
| `examples/websocket-chat.php` | WebSocket echo round-trip (imperative + declarative) |

Run with `php examples/<name>.php`. The HTTP demos hit public APIs (jsonplaceholder, GitHub) and need network access.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan   # PHPStan level 9
composer check     # both
```

## Status

v0.2.0. Server-Sent Events and WebSocket shipped on top of v0.1.0's core (Animate model + auto-graph scopes + HTTP/File/Stream/Process facades).

## License

MIT — see [LICENSE](LICENSE).
