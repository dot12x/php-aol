# Sync

Async-aware concurrency primitives. Each suspends the calling Fiber while waiting — other work in the scope continues running.

All types live under `Aol\Sync\` except `Queue` and `Topic`, which live under `Aol\Queue\`.

---

## Mutex

Ensures only one caller runs a critical section at a time.

```php
<?php

use Aol\Sync\Mutex;

$mu = new Mutex();

$result = $mu->withLock(function () use (&$counter) {
    $counter++;
    return $counter;
});
```

`withLock(callable $fn): mixed` — acquires the lock, runs `$fn`, releases on return or exception.

---

## Semaphore

Limits the number of concurrent callers.

```php
<?php

use Aol\Aol;
use Aol\Http;
use Aol\Sync\Semaphore;

$sem = new Semaphore(permits: 10);

$results = Aol::scope(function () use ($sem, $urls) {
    return array_map(
        fn($url) => $sem->withPermit(fn() => Http::get($url)),
        $urls,
    );
});
```

`withPermit(callable $fn): mixed` — acquires one permit, runs `$fn`, releases on return or exception.

---

## Barrier

Makes N parties wait until all of them have arrived.

```php
<?php

use Aol\Aol;
use Aol\Sync\Barrier;

$barrier = new Barrier(parties: 3);

Aol::scope(function () use ($barrier) {
    Aol::async(function () use ($barrier) {
        doPhaseOne('worker-1');
        $barrier->wait();
        doPhaseTwo('worker-1');
    });

    Aol::async(function () use ($barrier) {
        doPhaseOne('worker-2');
        $barrier->wait();
        doPhaseTwo('worker-2');
    });

    Aol::async(function () use ($barrier) {
        doPhaseOne('worker-3');
        $barrier->wait();
        doPhaseTwo('worker-3');
    });
});
```

`wait(): void` — suspends the Fiber until `parties` count callers have all called `wait()`, then releases all of them.

---

## WaitGroup

Fan-out and wait for all units to complete (Go-style).

```php
<?php

use Aol\Aol;
use Aol\Sync\WaitGroup;

$wg = new WaitGroup();

foreach ($jobs as $job) {
    $wg->add();
    Aol::async(function () use ($job, $wg) {
        try {
            process($job);
        } finally {
            $wg->done();
        }
    });
}

$wg->wait();   // suspends until all done() calls balance the add() calls
```

| Method | Description |
|---|---|
| `add(int $delta = 1)` | Increment the counter |
| `done()` | Decrement the counter |
| `wait()` | Suspend until counter reaches zero |

---

## Queue

FIFO single-consumer queue with backpressure.

```php
<?php

use Aol\Aol;
use Aol\Queue\Queue;

$q = new Queue(capacity: 1024);

// Producer
Aol::async(function () use ($q) {
    foreach (range(1, 10000) as $i) {
        $q->push(new Job($i));   // suspends if full (backpressure)
    }
    $q->close();
});

// Consumer
foreach ($q as $job) {
    $pool->process($job);   // iterates until queue is closed and drained
}
```

| Method | Description |
|---|---|
| `push(mixed $item)` | Enqueue item; suspends if at capacity |
| `pop(): mixed` | Dequeue item; suspends if empty |
| `close()` | Signal no more items; consumers exit loop after drain |

`Queue` is iterable — `foreach ($q as $item)` exits when the queue is closed and empty.

---

## Topic

Pub/sub fan-out. Each subscriber gets every message published after their subscription.

```php
<?php

use Aol\Aol;
use Aol\Queue\Topic;

$events = new Topic();

$logSub     = $events->subscribe();   // Queue
$metricsSub = $events->subscribe();   // Queue

Aol::async(function () use ($logSub) {
    foreach ($logSub as $event) {
        error_log((string) $event);
    }
});

Aol::async(function () use ($metricsSub) {
    foreach ($metricsSub as $event) {
        $metrics->record($event);
    }
});

$events->publish(new UserSignedIn($userId));
$events->publish(new OrderPlaced($orderId));
```

| Method | Description |
|---|---|
| `subscribe(): Queue` | Create a new subscriber queue |
| `publish(mixed $message)` | Send message to all current subscribers |
