<?php

declare(strict_types=1);

namespace Aol\WebSocket\Attribute;

/** Method called per received Aol\WebSocket\Message. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OnMessage
{
}
