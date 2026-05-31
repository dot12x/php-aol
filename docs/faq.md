# FAQ

## Why no `await`, `yield`, or `then()`?

Design choice. The library is attribute-driven: users annotate methods with `#[Async]`, and the scope + auto-graph handle execution order. There is no value in also exposing a manual "wait here" primitive — it would duplicate what the scope already does, and it would encourage imperative async orchestration instead of declarative structure.

If you need a value from a `Pending` mid-scope, either pass it as a dependency to another async call (auto-graph resolves it) or open a smaller inner scope around just that work.

## Why Fibers + Revolt instead of threads?

PHP's `ext-parallel` (threads) requires a ZTS-compiled PHP build, which almost no production host provides. Threads also require explicit shared-memory management, which makes PHP code significantly harder to reason about.

Fibers give cooperative concurrency with a familiar call stack, no shared memory problems, and work on every standard PHP 8.4+ install. For true CPU parallelism, shell out via `Aol\Process` to a separate PHP process.

Revolt is the event loop standard that amphp v3 and Fibers are built around — it is the lowest-common-denominator async substrate for modern PHP.

## Can I use this with Symfony or Laravel?

Yes. Call `Aol::useContainer($psr11Container)` to wire your framework's DI container. AOL will resolve constructor dependencies through it when you call `Aol::wrap(MyService::class)`.

Classes annotated with `#[Async]` work the same inside a framework controller, command, or service — as long as the code that calls them runs inside `Aol::scope()`.

```php
<?php

use Aol\Aol;

// In your bootstrap (e.g. Kernel boot, service provider)
Aol::useContainer($container);

// In a controller action
public function index(): Response
{
    $result = Aol::scope(function () {
        return $this->asyncService->fetch('https://api.example.com');
    });

    return new JsonResponse($result);
}
```

## What is the performance profile?

Single-threaded, non-blocking I/O. The library excels at I/O-heavy workloads: parallel HTTP requests, concurrent file operations, process management, socket servers. If you have 100 outbound HTTP calls to make, running them concurrently in a scope is dramatically faster than running them sequentially.

It is not a solution for CPU-bound parallelism. Heavy computation blocks the event loop. For CPU work, use `Process::run()` to shell out to a worker process.

## Is php-aol production-ready?

v0.1.0, pre-stable. The API is feature-complete and tested (236 tests, PHPStan level 9), but it has not yet been battle-tested across diverse production workloads. Use with appropriate caution; report issues on GitHub.

## What is coming in v0.2.0?

- WebSocket client
- Server-Sent Events (SSE)
- Recursive `File::watch()` and `#[OnFileChange(recursive: true)]`
- More real-world integration testing

## Why is there no `Aol::value($pending)` escape hatch?

It was explicitly rejected. An escape hatch that forces a `Pending` to resolve mid-scope would undermine structured concurrency — it creates an implicit barrier that the scope knows nothing about, making execution order hard to reason about.

If you think you need it, the usual fix is one of:
- Pass the `Pending` as a dependency to the next async call (auto-graph resolves the ordering).
- Open a smaller inner `Aol::scope()` around just the work you need resolved.
- Restructure so the value is returned from the outer scope.

## Why can't I use `#[OnFileChange]` with `recursive: true` yet?

The poll-based watcher (`filemtime` + `crc32b`) operates on individual paths. Recursive directory watching requires walking the tree on every poll tick, which needs more careful performance design. It is deferred to a later release. The `recursive` parameter is accepted by the attribute but has no effect in v0.1.0.
