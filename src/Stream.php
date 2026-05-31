<?php

declare(strict_types=1);

namespace Aol;

use Aol\Stream\Connection;
use Aol\Stream\Listener;
use function Amp\Socket\connect;
use function Amp\Socket\connectTls;
use function Amp\Socket\listen;

/**
 * Async TCP/UDP/Unix/TLS sockets.
 *
 *   $conn = Stream::connect('tcp://api.example.com:80');
 *   $conn = Stream::connect('tls://api:443');
 *   $conn = Stream::connect('unix:///var/run/app.sock');
 *
 *   $server = Stream::listen('tcp://0.0.0.0:8080');
 *   foreach ($server->accept() as $client) { ... }
 */
final class Stream
{
    public static function connect(string $uri): Connection
    {
        if (\str_starts_with($uri, 'tls://')) {
            return new Connection(connectTls(\substr($uri, 6)));
        }
        return new Connection(connect($uri));
    }

    public static function listen(string $uri): Listener
    {
        return new Listener(listen($uri));
    }
}
