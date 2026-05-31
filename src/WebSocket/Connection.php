<?php

declare(strict_types=1);

namespace Aol\WebSocket;

use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\WebsocketCloseCode;

/**
 * A live WebSocket connection. Send text or binary frames, iterate
 * incoming messages, close cleanly.
 *
 * Lifetime is owned by the surrounding Aol::scope(): scope cancel
 * closes the connection. The messages() iterator terminates when
 * the peer closes or close() is called locally.
 */
final class Connection
{
    public function __construct(private readonly WebsocketConnection $amp)
    {
    }

    public bool $isAlive {
        get => !$this->amp->isClosed();
    }

    public function send(string $text): void
    {
        $this->amp->sendText($text);
    }

    public function sendBinary(string $bytes): void
    {
        $this->amp->sendBinary($bytes);
    }

    /**
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        $i = 0;
        while (($msg = $this->amp->receive()) !== null) {
            yield $i++ => new Message(
                payload: $msg->buffer(),
                binary: $msg->isBinary(),
            );
        }
    }

    public function close(int $code = WebsocketCloseCode::NORMAL_CLOSE, string $reason = ''): void
    {
        $this->amp->close($code, $reason);
    }
}
