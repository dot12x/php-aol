<?php

declare(strict_types=1);

namespace Aol\Stream;

use Aol\Stream\Framing\Framing;
use Amp\Socket\Socket;

/**
 * Bidirectional byte stream — TCP/TLS/Unix/UDP client connection.
 */
final class Connection
{
    private ?Framing $framing = null;

    public function __construct(private readonly Socket $socket)
    {
    }

    public function read(int $limit = 8192): ?string
    {
        return $this->socket->read(limit: \max(1, $limit));
    }

    public function readAll(): string
    {
        $out = '';
        while (($chunk = $this->socket->read()) !== null) {
            $out .= $chunk;
        }
        return $out;
    }

    public function write(string $data): void
    {
        $this->socket->write($data);
    }

    public function close(): void
    {
        if (!$this->socket->isClosed()) {
            $this->socket->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function withFraming(Framing $framing): self
    {
        $copy = clone $this;
        $copy->framing = $framing;
        return $copy;
    }

    /**
     * Read one logical frame using the configured framing.
     */
    public function readFrame(): ?string
    {
        if ($this->framing === null) {
            throw new \LogicException('No framing configured — use withFraming() first.');
        }
        return $this->framing->readFrame($this);
    }

    public function writeFrame(string $payload): void
    {
        if ($this->framing === null) {
            throw new \LogicException('No framing configured — use withFraming() first.');
        }
        $this->framing->writeFrame($this, $payload);
    }
}
