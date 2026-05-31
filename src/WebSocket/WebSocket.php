<?php

declare(strict_types=1);

namespace Aol\WebSocket;

use Aol\Internal\WebSocket\Client;

/**
 * Async WebSocket client facade.
 *
 *     $ws = WebSocket::connect('wss://example.com/socket');
 *     $ws->send('hello');
 *     foreach ($ws->messages() as $msg) { ... }
 *     $ws->close();
 *
 * Must be called inside Aol::scope() — the scope owns the connection
 * lifetime.
 */
final class WebSocket
{
    /**
     * @param array<string, string> $headers
     */
    public static function connect(string $url, array $headers = []): Connection
    {
        return new Connection(Client::connect($url, $headers));
    }
}
