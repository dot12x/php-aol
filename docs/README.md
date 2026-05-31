# php-aol Documentation

PHP 8.4+ attribute-driven async/concurrency library built on Fibers + Revolt.

## Contents

| File | Description |
|---|---|
| [getting-started.md](./getting-started.md) | Install, first scope, first wrapped class |
| [concepts.md](./concepts.md) | The four primitives, mental model, auto-graph, cancellation |
| [attributes.md](./attributes.md) | Full attribute reference grouped by purpose |
| [http.md](./http.md) | HTTP client — static facade, declarative interface, Server-Sent Events |
| [websocket.md](./websocket.md) | WebSocket client — imperative connect + declarative `#[WebSocket]` |
| [file.md](./file.md) | Filesystem facade — I/O, locking, temp files, watch |
| [process.md](./process.md) | Child processes — one-shot, streaming, declarative daemon |
| [stream.md](./stream.md) | TCP/TLS/UDP/Unix sockets and protocol framing |
| [sync.md](./sync.md) | Async-aware Mutex, Semaphore, Barrier, WaitGroup, Queue, Topic |
| [time.md](./time.md) | sleep, deadline, interval |
| [testing.md](./testing.md) | FakeClock, stub HTTP client, testing async code |
| [phpstan.md](./phpstan.md) | PHPStan extension — setup and what it covers |
| [faq.md](./faq.md) | Common questions |

For runnable code, see the [`examples/`](../examples/) directory at the repo root.
