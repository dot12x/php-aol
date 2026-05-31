# Time

`Aol\Time` provides async-aware time utilities. All durations are `int` or `float` seconds — never strings.

---

## `Time::sleep`

Suspend the current Fiber for N seconds. Other work in the scope continues running.

```php
<?php

use Aol\Aol;
use Aol\Time;

Aol::scope(function () {
    Time::sleep(1);     // 1 second
    Time::sleep(0.5);   // 500 ms
});
```

When called inside a scope, `sleep` is scope-cancellable — if the scope is cancelled during the sleep, `AolCancelledException` is raised and the sleep is interrupted.

When called outside a scope, it falls back to a plain Revolt delay.

---

## `Time::deadline`

Run a callable with a hard time limit. Throws `AolTimeoutException` if the body does not complete in time.

```php
<?php

use Aol\Aol;
use Aol\Time;
use Aol\Exception\AolTimeoutException;

$result = Aol::scope(function () {
    try {
        return Time::deadline(5, function () {
            return expensiveOperation();
        });
    } catch (AolTimeoutException) {
        return 'timed out';
    }
});
```

Signature:

```php
Time::deadline(int|float $seconds, callable $body): mixed
```

The body is cancelled cooperatively — it must reach a suspension point (I/O, sleep) for cancellation to take effect.

---

## `Time::interval`

Call a callable repeatedly every N seconds. Runs as a background async task — the scope does not wait for it on natural close; instead, the scope cancel-drains it on exit.

```php
<?php

use Aol\Aol;
use Aol\Time;

Aol::scope(function () {
    Time::interval(5, function () {
        echo "heartbeat\n";
    });

    Time::sleep(60);   // scope runs for 60 seconds; interval fires ~12 times
});
// interval is cancelled here
```

Signature:

```php
Time::interval(int|float $seconds, callable $body): void
```

Use `Aol::asyncBackground` directly (instead of `Time::interval`) when you need a reference to the background `Pending`, e.g., to cancel a specific interval selectively.

---

## Comparison

| | `sleep` | `deadline` | `interval` |
|---|---|---|---|
| Blocks scope close | Yes (can be cancelled) | Yes (until done or timeout) | No (background) |
| Throws on cancel | `AolCancelledException` | `AolTimeoutException` | silently drained |
| Returns | `void` | body return value | `void` |

---

## Testing time-dependent code

Use `Aol\Test\FakeClock` to run time-dependent tests without real waiting. See [testing.md](./testing.md).
