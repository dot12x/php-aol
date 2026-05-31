<?php

declare(strict_types=1);

namespace Aol\Stream;

use Amp\Socket\ServerSocket;

/**
 * Server-side listener returned by Stream::listen().
 */
final class Listener
{
    public function __construct(private readonly ServerSocket $server)
    {
    }

    /**
     * Generator of incoming connections. Each foreach iteration awaits
     * a new client.
     *
     * @return \Generator<int, Connection>
     */
    public function accept(): \Generator
    {
        $i = 0;
        while (($client = $this->server->accept()) !== null) {
            yield $i++ => new Connection($client);
        }
    }

    public function close(): void
    {
        if (!$this->server->isClosed()) {
            $this->server->close();
        }
    }

    public function address(): string
    {
        return (string) $this->server->getAddress();
    }
}
