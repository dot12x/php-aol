<?php

declare(strict_types=1);

namespace Aol\WebSocket\Attribute;

/**
 * Method called once when the WebSocket closes (peer or local).
 * Signature: `(?int $code, ?string $reason): void` — both null when
 * the peer drops without a close frame.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OnClose
{
}
