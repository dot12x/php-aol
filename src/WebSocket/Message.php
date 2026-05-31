<?php

declare(strict_types=1);

namespace Aol\WebSocket;

/**
 * A single WebSocket message. `binary` distinguishes text frames
 * (UTF-8 payload) from binary frames (raw bytes).
 */
final readonly class Message
{
    public function __construct(
        public string $payload,
        public bool $binary = false,
    ) {
    }
}
