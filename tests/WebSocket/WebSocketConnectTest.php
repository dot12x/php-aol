<?php

declare(strict_types=1);

namespace Aol\Tests\WebSocket;

use Aol\Aol;
use Aol\Internal\WebSocket\Client;
use Aol\WebSocket\Connection;
use Aol\WebSocket\WebSocket;
use Amp\Cancellation;
use Amp\Websocket\Client\WebsocketConnection as AmpConnection;
use Amp\Websocket\Client\WebsocketConnector;
use Amp\Websocket\Client\WebsocketHandshake;
use PHPUnit\Framework\TestCase;

final class WebSocketConnectTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::reset();
    }

    public function testSendsAndReceivesViaStubConnector(): void
    {
        $stub = new FakeWebsocketConnection(incoming: ['hello', 'world']);
        Client::useConnector(new class($stub) implements WebsocketConnector {
            public function __construct(private AmpConnection $stub)
            {
            }
            public function connect(WebsocketHandshake $handshake, ?Cancellation $cancellation = null): AmpConnection
            {
                return $this->stub;
            }
        });

        $received = Aol::scope(function () {
            $ws = WebSocket::connect('wss://example.test/socket');
            self::assertInstanceOf(Connection::class, $ws);
            $ws->send('ping');
            $out = [];
            foreach ($ws->messages() as $m) {
                $out[] = $m->payload;
            }
            $ws->close();
            return $out;
        });

        self::assertSame(['hello', 'world'], $received);
        self::assertSame(['ping'], $stub->sent);
        self::assertTrue($stub->isClosed());
    }

    public function testSendBinaryRoutesToAmpSendBinary(): void
    {
        $stub = new FakeWebsocketConnection();
        Client::useConnector(new class($stub) implements WebsocketConnector {
            public function __construct(private AmpConnection $stub)
            {
            }
            public function connect(WebsocketHandshake $handshake, ?Cancellation $cancellation = null): AmpConnection
            {
                return $this->stub;
            }
        });

        Aol::scope(function () {
            $ws = WebSocket::connect('wss://example.test/socket');
            $ws->sendBinary("\x00\xff");
        });

        self::assertSame(["\x00\xff"], $stub->sentBinary);
    }
}
