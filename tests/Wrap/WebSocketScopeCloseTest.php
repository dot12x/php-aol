<?php

declare(strict_types=1);

namespace Aol\Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Internal\WebSocket\Client;
use Aol\Tests\WebSocket\FakeWebsocketConnection;
use Aol\Time;
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
final class IdleSocketClient
{
    #[WsConnection]
    public ?Connection $ws = null;

    public bool $opened = false;

    #[OnOpen]
    public function connected(): void
    {
        $this->opened = true;
    }

    #[OnMessage]
    public function rx(Message $m): void
    {
    }
}

final class WebSocketScopeCloseTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::reset();
    }

    /**
     * Regression: when a declarative WebSocket client is open but the peer is
     * idle (no frames arriving), the scope must still close in bounded time.
     * Before the fix the WS receive loop blocked inside amp->receive() forever,
     * because scope cancellation was not propagated to the connection — the
     * scope hung on drainBackgroundSilently waiting for the loop that would
     * never exit.
     */
    public function testScopeClosesWhilePeerIsIdle(): void
    {
        $idleStub = new FakeWebsocketConnection(incoming: [], blockWhenDrained: true);
        Client::useConnector(new class($idleStub) implements WebsocketConnector {
            public function __construct(private AmpConnection $stub)
            {
            }
            public function connect(WebsocketHandshake $h, ?Cancellation $c = null): AmpConnection
            {
                return $this->stub;
            }
        });

        $start = \microtime(true);
        Aol::scope(function (): void {
            $w = Aol::wrap(IdleSocketClient::class);
            Time::sleep(0.05);
            $_ = $w;
        });
        $elapsed = \microtime(true) - $start;

        self::assertLessThan(
            0.5,
            $elapsed,
            'scope must close in bounded time even when the WebSocket peer is silent',
        );
        self::assertTrue($idleStub->isClosed(), 'Wrapper must close the connection on scope cancel');
    }
}
