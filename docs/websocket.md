# WebSocket

Bidirectional message stream over a single TCP connection. The module ships both an imperative facade and an attribute-driven declarative surface, matching the Process pattern.

Underlying transport: `amphp/websocket-client`. Never appears in public signatures — Aol's `Connection` and `Message` are the only types you see.

---

## Imperative — `WebSocket::connect()`

```php
<?php

use Aol\Aol;
use Aol\WebSocket\WebSocket;

Aol::scope(function () {
    $ws = WebSocket::connect('wss://example.com/socket');
    $ws->send('hello');

    foreach ($ws->messages() as $msg) {
        if ($msg->binary) {
            handleBytes($msg->payload);
        } else {
            echo $msg->payload, "\n";
        }
    }
    $ws->close();
});
```

`Aol::scope()` owns the connection's lifetime: when the scope closes the connection is closed and the iterator stops.

### `Connection`

| Member | Type | Description |
|---|---|---|
| `$ws->send(string)` | `void` | Send a text frame |
| `$ws->sendBinary(string)` | `void` | Send a binary frame |
| `$ws->messages()` | `iterable<int, Message>` | Iterate received messages until peer/local close |
| `$ws->close(int $code = 1000, string $reason = '')` | `void` | Initiate a clean close |
| `$ws->isAlive` | `bool` | True until either side closes (property hook) |

### `Message`

```php
final readonly class Message
{
    public string $payload;
    public bool   $binary = false;
}
```

---

## Declarative — `#[WebSocket]` on a wrapped class

```php
<?php

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\WebSocket\Attribute\{WebSocket, WsConnection, OnOpen, OnMessage, OnClose};
use Aol\WebSocket\{Connection, Message};

#[Worker]
#[WebSocket('wss://example.com/socket')]
class ChatClient
{
    #[WsConnection]
    private ?Connection $ws = null;

    #[OnOpen]
    public function connected(): void
    {
        $this->ws?->send('hello');
    }

    #[OnMessage]
    public function rx(Message $msg): void
    {
        echo $msg->payload, "\n";
    }

    #[OnClose]
    public function bye(?int $code, ?string $reason): void
    {
        /* peer dropped or local close */
    }
}

Aol::scope(fn() => Aol::wrap(ChatClient::class));
```

### Lifecycle

When `Aol::wrap()` animates the class, the Wrapper:

1. Opens one connection per pool instance via the class-level `#[WebSocket(url)]`.
2. Hydrates the `#[WsConnection]`-tagged property with the live `Connection`.
3. Calls every `#[OnOpen]` method synchronously.
4. Launches a background fiber per instance that pulls frames and invokes every `#[OnMessage]` method per frame.
5. When the loop terminates (peer close, local close, or scope cancel), every `#[OnClose]` method runs once with `(?int $code, ?string $reason)`.
6. On scope close, the Wrapper closes every still-open connection before `#[OnSleep]`.

### Attribute reference

| Attribute | Where | Effect |
|---|---|---|
| `#[WebSocket(string $url)]` | class | Animate this class as a WebSocket client (one connection per pool instance) |
| `#[WsConnection]` | property | Wrapper hydrates the property with the live `Connection` before `#[OnOpen]` |
| `#[OnOpen]` | method | `(): void` — runs synchronously after connect |
| `#[OnMessage]` | method | `(Message $m): void` — runs per received frame |
| `#[OnClose]` | method | `(?int $code, ?string $reason): void` — runs once when the stream ends |

---

## Testing

Swap the connector to avoid real network calls:

```php
use Aol\Internal\WebSocket\Client;
use Amp\Websocket\Client\WebsocketConnector;

Client::useConnector($yourStubConnector);   // any WebsocketConnector
// ... run your scope ...
Client::reset();                             // restore default connector
```

See `tests/WebSocket/FakeWebsocketConnection.php` for a minimal fake `WebsocketConnection` implementing only the methods the Aol surface touches.

---

## What this module is NOT

- A **server**. Listening for incoming connections will belong in a future `php-aol/server` package.
- A **persistent reconnect** layer. Reconnect strategy is the consumer's responsibility (or future work). The `#[OnClose]` method is where you'd schedule a re-`wrap` if you wanted it.
- A protocol like STOMP, SignalR, or Phoenix Channels. AOL exposes raw text/binary frames; build your protocol layer on top.
