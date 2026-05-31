# Testing

## `FakeClock`

`Aol\Test\FakeClock` provides deterministic virtual time. Calls to `Time::sleep` inside a scope running under a `FakeClock` advance virtual time immediately — no real waiting occurs.

```php
<?php

use Aol\Test\FakeClock;
use Aol\Test\runScope;
use Aol\Time;

$clock = new FakeClock();

$result = runScope($clock, function () {
    Time::sleep(30);    // returns instantly; virtual clock advances 30s
    return 'done';
});

echo $result;   // "done"
echo $clock->now();   // 30.0
```

### Methods

| Method | Description |
|---|---|
| `now(): float` | Current virtual time in seconds |
| `advance(float $seconds)` | Push virtual time forward manually |
| `sleep(float $seconds)` | Called internally by `Time::sleep`; advances virtual time |

### `runScope(FakeClock $clock, callable $body): mixed`

Runs `$body` inside an `Aol::scope()` with the fake clock injected. Returns the scope's result.

---

## Testing code that uses `Time::sleep`

```php
<?php

use Aol\Test\{FakeClock, runScope};
use Aol\Attribute\{Async, Retry};
use Aol\Aol;

class RetryingService
{
    private int $attempts = 0;

    #[Async]
    #[Retry(times: 3, backoff: 'fixed', delay: 5)]
    public function fetch(string $url): string
    {
        $this->attempts++;
        if ($this->attempts < 3) {
            throw new \RuntimeException('temporary failure');
        }
        return 'ok';
    }
}

function testRetryWithDelay(): void
{
    $clock = new FakeClock();

    $service = Aol::wrap(RetryingService::class);

    $result = runScope($clock, function () use ($service) {
        return $service->fetch('https://api.example.com');
    });

    assert($result === 'ok');
    assert($clock->now() >= 10.0);   // two retries × 5s delay = 10s virtual time
}
```

No real time elapses. The entire test completes in milliseconds.

---

## Stub HTTP client

Inject a fake HTTP client to avoid real network calls during tests. See [http.md — Testing](./http.md#testing) for the full example.

```php
<?php

use Aol\Http;

Http::useClient($stubClient);

// run your test ...

Http::useClient(null);   // reset to real client
```

---

## General testing patterns

**Test a scope's return value:**

```php
<?php

use Aol\Aol;

$result = Aol::scope(fn() => computeSomething());
assertEquals('expected', $result);
```

**Test that an exception propagates:**

```php
<?php

use Aol\Aol;
use Aol\Attribute\Async;

class Exploding
{
    #[Async]
    public function run(): void
    {
        throw new \RuntimeException('boom');
    }
}

$e = Aol::wrap(Exploding::class);

try {
    Aol::scope(fn() => $e->run());
    fail('expected exception');
} catch (\RuntimeException $ex) {
    assertEquals('boom', $ex->getMessage());
}
```

**Test cancellation / timeout:**

```php
<?php

use Aol\Aol;
use Aol\Time;
use Aol\Exception\AolTimeoutException;

try {
    Aol::scope(function () {
        Time::deadline(0.001, fn() => Time::sleep(10));
    });
} catch (AolTimeoutException) {
    // expected
}
```
