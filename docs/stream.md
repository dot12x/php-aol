# Stream

`Aol\Stream` provides async TCP, TLS, UDP, and Unix domain socket support.

Underlying transport: `amphp/socket`.

---

## Connecting

```php
<?php

use Aol\Stream;

$conn = Stream::connect('tcp://redis:6379');
$conn = Stream::connect('tls://api.example.com:443');
$conn = Stream::connect('unix:///var/run/app.sock');
$conn = Stream::connect('udp://dns:53');
```

### `Connection`

| Method | Description |
|---|---|
| `read(int $limit = 8192): ?string` | Read up to N bytes; `null` on EOF |
| `write(string $data): void` | Write bytes |
| `close(): void` | Close the connection |

```php
<?php

use Aol\Stream;

$conn = Stream::connect('tcp://redis:6379');
$conn->write("PING\r\n");
$response = $conn->read();
$conn->close();
```

---

## Listening

```php
<?php

use Aol\Aol;
use Aol\Stream;

Aol::scope(function () {
    $server = Stream::listen('tcp://0.0.0.0:8080');

    echo 'Listening on ' . $server->address() . "\n";  // "0.0.0.0:8080"

    foreach ($server->accept() as $client) {
        handleClient($client);   // Pending — runs in parallel
    }
});
```

### `Listener`

| Method | Description |
|---|---|
| `address(): string` | Bound address in `host:port` form |
| `accept(): \Generator<int, Connection>` | Yields incoming connections |
| `close(): void` | Stop accepting new connections |

---

## Protocol framing

Raw `Connection` gives you bytes. `withFraming()` layers a message-oriented protocol on top.

```php
<?php

use Aol\Stream;
use Aol\Stream\Framing\{Line, Length, Json};

// Line-delimited (e.g. Redis, SMTP, NATS)
$conn = Stream::connect('tcp://redis:6379')
    ->withFraming(new Line("\r\n"));

$conn->writeFrame("PING");
$pong = $conn->readFrame();   // "PONG"

// Length-prefixed (binary protocols)
$conn = Stream::connect('tcp://service:9000')
    ->withFraming(new Length(4));   // 4-byte big-endian length prefix

$conn->writeFrame($binaryPayload);
$reply = $conn->readFrame();

// JSON over line-delimited (newline-delimited JSON)
$conn = Stream::connect('tcp://service:9001')
    ->withFraming(new Json());

$conn->writeFrame(['action' => 'ping']);
$reply = $conn->readFrame();   // already decoded to array
```

### Built-in framings

| Class | Description |
|---|---|
| `Line(string $delimiter = "\n")` | Reads/writes until delimiter; strips delimiter from result |
| `Length(int $width)` | Big-endian length prefix; width 1, 2, 4, or 8 bytes |
| `Json(string $delimiter = "\n")` | `Line` + JSON encode/decode |

Framing state is tracked per connection (`spl_object_id`). Each call to `withFraming()` returns a new framed connection object that wraps the original.

---

## Example: simple Redis PING

```php
<?php

use Aol\Aol;
use Aol\Stream;
use Aol\Stream\Framing\Line;

$response = Aol::scope(function () {
    $redis = Stream::connect('tcp://127.0.0.1:6379')
        ->withFraming(new Line("\r\n"));

    $redis->writeFrame("PING");
    return $redis->readFrame();
});

echo $response . "\n";  // +PONG
```

---

## Example: echo server

```php
<?php

use Aol\Aol;
use Aol\Stream;
use Aol\Stream\Framing\Line;

Aol::scope(function () {
    $server = Stream::listen('tcp://0.0.0.0:7000');

    foreach ($server->accept() as $conn) {
        Aol::async(function () use ($conn) {
            $framed = $conn->withFraming(new Line());
            while (($msg = $framed->readFrame()) !== null) {
                $framed->writeFrame('echo: ' . $msg);
            }
        });
    }
});
```
