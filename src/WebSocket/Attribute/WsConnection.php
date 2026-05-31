<?php

declare(strict_types=1);

namespace Aol\WebSocket\Attribute;

/**
 * Property-level: the Wrapper hydrates this property with the live
 * Aol\WebSocket\Connection after the WebSocket opens, before #[OnOpen]
 * fires. Property must be typed Aol\WebSocket\Connection (or nullable).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class WsConnection
{
}
