<?php

declare(strict_types=1);

namespace Aol\WebSocket\Attribute;

/**
 * Class-level: declares this wrapped class is animated as a WebSocket
 * client. The connection is opened per pool instance at Aol::wrap()
 * time and closed when the scope closes.
 *
 * Pair with #[WsConnection] on a property to receive the live
 * Aol\WebSocket\Connection, and #[OnOpen]/#[OnMessage]/#[OnClose] on
 * methods for lifecycle hooks.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class WebSocket
{
    public function __construct(public string $url)
    {
    }
}
