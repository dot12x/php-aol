# Concepts

## The four primitives

| Primitive | What it is |
|---|---|
| `Aol::scope(callable)` | Arena. Opens a structured-concurrency region; returns the resolved result when it closes. |
| `Aol::wrap(string\|object)` | Animate. Creates a proxy for a class; `#[Async]` methods on it return `Pending` instead of real values. |
| `Aol::async(callable, ...$deps)` | Schedule an ad-hoc callable inside the current scope. Returns a `Pending`. |
| `Pending<T>` | Value-not-yet-here. A placeholder for work in flight. Mostly invisible — you pass it around; the library resolves it. |

## Mental model

- **Class** — a blueprint, asleep. Plain PHP with attributes that describe how it behaves when alive.
- **`Aol::wrap()`** — animate the blueprint. AOL instantiates it, runs `#[OnAwake]` hooks, attaches any signal/tick/file-watch handlers, and returns a proxy.
- **`Aol::scope()`** — the arena where animated objects live. Async calls inside a scope return `Pending` values; the auto-graph orchestrates them. When the scope closes, every `Pending` resolves (or the scope crashes).
- **`Pending`** — a value not yet here. Largely invisible: you pass `Pending` into other async calls and AOL builds the dependency graph automatically.

## Auto-graph

Passing a `Pending` as an argument to another async call automatically declares a dependency. The library waits for the dependency before starting the dependent work. You do not declare ordering explicitly.

```php
<?php

use Aol\Aol;
use Aol\Attribute\Async;

class Fetcher
{
    #[Async]
    public function fetch(string $url): string { /* ... */ }

    #[Async]
    public function parse(string $html): array { /* ... */ }
}

$f = Aol::wrap(Fetcher::class);

$result = Aol::scope(function () use ($f) {
    $html   = $f->fetch('https://example.com');  // Pending<string>
    $parsed = $f->parse($html);                   // waits for $html automatically
    return $parsed;                               // scope resolves → real array
});
```

## Why no `await` / `yield` / `then()`

This library does not expose those constructs by design. They imply that the programmer manually orchestrates async steps — deciding what to wait for and when. AOL's position is that the scope + auto-graph should handle that. When the scope closes, everything resolves. When a `Pending` is passed as an argument, the dependency is declared. There is no value in also having an explicit "wait here" call.

If you find yourself wanting an escape hatch to resolve a `Pending` mid-scope, that is a signal to restructure: either use `Aol::async()` with explicit `$deps` arguments, or split your work across a smaller inner scope.

## `Aol::async` vs `Aol::asyncBackground`

| | `Aol::async` | `Aol::asyncBackground` |
|---|---|---|
| Returns | `Pending<T>` | `Pending<T>` |
| Scope waits on close | Yes — scope will not close until it resolves | No — scope cancel-drains it on close |
| Use for | Work that produces a value the scope returns | "Until the scope ends" — timers, signal listeners, intervals |

```php
<?php

use Aol\Aol;
use Aol\Time;

Aol::scope(function () {
    // Runs every second until scope closes
    Time::interval(1.0, function () {
        echo "tick\n";
    });

    // Ad-hoc async — scope waits for this
    $result = Aol::async(fn() => expensiveWork());

    Time::sleep(5);
    return $result;
});
```

## Cancellation

Scopes propagate cancellation downward. When a scope is cancelled (timeout, signal, or because a sibling failed), all `Pending` values inside it receive cancellation. Cancellation is cooperative — a Fiber must reach a suspension point (I/O, sleep, or explicit check) for cancellation to take effect.

`#[OnSignal(SIGTERM)]` lets an animated class react to cancellation at the OS level; `#[OnSleep]` runs cleanup when the scope closes normally or via cancellation.

## Error policy — one_for_all

If any `Pending` inside a scope fails with an unhandled exception, the scope cancels its remaining `Pending` values and re-throws the exception when the scope closes. There is no silent swallowing of partial failures. This is intentional: structured concurrency means the scope either completes cleanly or propagates the problem.

See [attributes.md](./attributes.md) for `#[Retry]` and `#[Timeout]`, which let individual methods handle their own transient failures before they escalate to scope cancellation.
