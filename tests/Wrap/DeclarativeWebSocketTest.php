<?php

declare(strict_types=1);

namespace Aol\Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Internal\WebSocket\Client;
use Aol\Tests\WebSocket\FakeWebsocketConnection;
use Aol\Time;
use Aol\WebSocket\Attribute\OnClose;
use Aol\WebSocket\Attribute\OnMessage;
use Aol\WebSocket\Attribute\OnOpen;
use Aol\WebSocket\Attribute\WebSocket;
use Aol\WebSocket\Attribute\WsConnection;
use Aol\WebSocket\Connection;
use Aol\WebSocket\Message;
use Amp\Cancellation;
use Amp\Websocket\Client\WebsocketConnection as AmpConnection;
use Amp\Websocket\Client\WebsocketConnector;
use Amp\Websocket\Client\WebsocketHandshake;
use PHPUnit\Framework\TestCase;

#[Worker]
#[WebSocket('wss://example.test/socket')]
final class ChatStub
{
    #[WsConnection]
    public ?Connection $ws = null;

    public bool $opened = false;

    /** @var list<string> */
    public array $received = [];

    public bool $closed = false;

    #[OnOpen]
    public function connected(): void
    {
        $this->opened = true;
        $this->ws?->send('hello');
    }

    #[OnMessage]
    public function rx(Message $m): void
    {
        $this->received[] = $m->payload;
    }

    #[OnClose]
    public function bye(?int $code, ?string $reason): void
    {
        $this->closed = true;
    }
}

final class DeclarativeWebSocketTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::reset();
    }

    public function testLifecycleFiresInOrder(): void
    {
        $stub = new FakeWebsocketConnection(incoming: ['a', 'b']);
        Client::useConnector(new class($stub) implements WebsocketConnector {
            public function __construct(private AmpConnection $stub)
            {
            }
            public function connect(WebsocketHandshake $handshake, ?Cancellation $cancellation = null): AmpConnection
            {
                return $this->stub;
            }
        });

        $chat = new ChatStub();

        Aol::scope(function () use ($chat) {
            $w = Aol::wrap($chat);
            Time::sleep(0.05);
            $_ = $w;
        });

        self::assertTrue($chat->opened, 'OnOpen must fire');
        self::assertSame(['a', 'b'], $chat->received, 'OnMessage must fire per incoming frame');
        self::assertTrue($chat->closed, 'OnClose must fire after stream ends');
        self::assertSame(['hello'], $stub->sent, 'WsConnection property must be hydrated before OnOpen');
    }
}
