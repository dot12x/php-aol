<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Time;
use Aol\WebSocket\Attribute\OnClose;
use Aol\WebSocket\Attribute\OnMessage;
use Aol\WebSocket\Attribute\OnOpen;
use Aol\WebSocket\Attribute\WebSocket as WsClient;
use Aol\WebSocket\Attribute\WsConnection;
use Aol\WebSocket\Connection;
use Aol\WebSocket\Message;
use Aol\WebSocket\WebSocket;

const ECHO_URL = 'wss://ws.postman-echo.com/raw';

echo "== imperative WebSocket::connect() demo ==\n";

Aol::scope(function () {
    $ws = WebSocket::connect(ECHO_URL);
    $ws->send('hello from aol');
    Aol::asyncBackground(function () use ($ws) {
        Time::sleep(1.0);
        $ws->close();
    });
    foreach ($ws->messages() as $msg) {
        echo "received: {$msg->payload}\n";
        break;
    }
    $ws->close();
});

echo "\n== declarative #[WebSocket] demo ==\n";

#[Worker]
#[WsClient(ECHO_URL)]
final class EchoClient
{
    #[WsConnection]
    public ?Connection $ws = null;

    #[OnOpen]
    public function hello(): void
    {
        echo "open — sending greeting\n";
        $this->ws?->send('ping');
    }

    #[OnMessage]
    public function rx(Message $m): void
    {
        echo "rx: {$m->payload}\n";
        $this->ws?->close();
    }

    #[OnClose]
    public function bye(?int $code, ?string $reason): void
    {
        echo "closed (code={$code})\n";
    }
}

Aol::scope(function () {
    $w = Aol::wrap(EchoClient::class);
    Time::sleep(1.5);
    $_ = $w;
});

echo "\ndone.\n";
