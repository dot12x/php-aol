<?php

declare(strict_types=1);

namespace Aol\Tests\WebSocket;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Websocket\Client\WebsocketConnection;
use Amp\Websocket\WebsocketCloseCode;
use Amp\Websocket\WebsocketCloseInfo;
use Amp\Websocket\WebsocketCount;
use Amp\Websocket\WebsocketMessage;
use Amp\Websocket\WebsocketTimestamp;

/**
 * @internal Test fake satisfying Amp\Websocket\Client\WebsocketConnection.
 *
 * Implements only the methods exercised by Aol\WebSocket\Connection:
 * receive(), sendText(), sendBinary(), close(), isClosed(). All other
 * interface methods throw — they're never reached in the Aol surface.
 *
 * @implements \IteratorAggregate<int, WebsocketMessage>
 */
final class FakeWebsocketConnection implements WebsocketConnection, \IteratorAggregate
{
    /** @var list<string> */
    public array $sent = [];

    /** @var list<string> */
    public array $sentBinary = [];

    public ?int $closeCode = null;

    public string $closeReason = '';

    private bool $closed = false;

    /** @var DeferredFuture<null>|null */
    private ?DeferredFuture $closeSignal = null;

    /**
     * @param list<string> $incoming text-frame payloads to return one at a time from receive()
     * @param bool $blockWhenDrained when true, receive() suspends until close() is called
     *                               instead of returning null — emulates a real long-lived
     *                               server connection waiting for the next frame.
     */
    public function __construct(
        private array $incoming = [],
        private readonly bool $blockWhenDrained = false,
    ) {
        if ($this->blockWhenDrained) {
            $this->closeSignal = new DeferredFuture();
        }
    }

    public function receive(?Cancellation $cancellation = null): ?WebsocketMessage
    {
        if ($this->incoming !== []) {
            $payload = \array_shift($this->incoming);
            return WebsocketMessage::fromText($payload);
        }
        if ($this->blockWhenDrained && $this->closeSignal !== null && !$this->closeSignal->isComplete()) {
            $this->closeSignal->getFuture()->await($cancellation);
        }
        return null;
    }

    public function sendText(string $data): void
    {
        $this->sent[] = $data;
    }

    public function sendBinary(string $data): void
    {
        $this->sentBinary[] = $data;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close(int $code = WebsocketCloseCode::NORMAL_CLOSE, string $reason = ''): void
    {
        $this->closed = true;
        $this->closeCode = $code;
        $this->closeReason = $reason;
        if ($this->closeSignal !== null && !$this->closeSignal->isComplete()) {
            $this->closeSignal->complete();
        }
    }

    public function getId(): int
    {
        return 0;
    }

    public function getLocalAddress(): SocketAddress
    {
        throw new \LogicException('not used by fake');
    }

    public function getRemoteAddress(): SocketAddress
    {
        throw new \LogicException('not used by fake');
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return null;
    }

    public function getCloseInfo(): WebsocketCloseInfo
    {
        throw new \LogicException('not used by fake');
    }

    public function isCompressionEnabled(): bool
    {
        return false;
    }

    public function streamText(ReadableStream $stream): void
    {
        throw new \LogicException('not used by fake');
    }

    public function streamBinary(ReadableStream $stream): void
    {
        throw new \LogicException('not used by fake');
    }

    public function ping(): void
    {
    }

    public function getCount(WebsocketCount $type): int
    {
        return 0;
    }

    public function getTimestamp(WebsocketTimestamp $type): float
    {
        return 0.0;
    }

    public function onClose(\Closure $onClose): void
    {
    }

    public function getHandshakeResponse(): Response
    {
        throw new \LogicException('not used by fake');
    }

    /**
     * @return \Generator<int, WebsocketMessage, void, void>
     */
    public function getIterator(): \Generator
    {
        while (($m = $this->receive()) !== null) {
            yield $m;
        }
    }
}
