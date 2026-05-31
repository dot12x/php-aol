<?php

declare(strict_types=1);

namespace Aol\Internal\WebSocket;

use Amp\Websocket\Client\Rfc6455Connector;
use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\Client\WebsocketConnector;
use Amp\Websocket\Client\WebsocketHandshake;

/**
 * @internal Adapter that builds Amp WebSocket connections. Centralized
 * so tests can swap the connector via useConnector().
 */
final class Client
{
    private static ?WebsocketConnector $connector = null;

    /**
     * @param array<string, string> $headers
     */
    public static function connect(string $url, array $headers = []): WebsocketConnection
    {
        $handshake = new WebsocketHandshake($url);
        foreach ($headers as $name => $value) {
            if ($name === '') {
                continue;
            }
            $handshake = $handshake->withHeader($name, $value);
        }
        return self::connector()->connect($handshake, null);
    }

    public static function useConnector(WebsocketConnector $connector): void
    {
        self::$connector = $connector;
    }

    public static function reset(): void
    {
        self::$connector = null;
    }

    private static function connector(): WebsocketConnector
    {
        if (self::$connector === null) {
            self::$connector = new Rfc6455Connector();
        }
        return self::$connector;
    }
}
