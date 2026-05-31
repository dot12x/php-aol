<?php

declare(strict_types=1);

namespace Aol\WebSocket\Attribute;

/** Method called once per pool instance after the WebSocket opens. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OnOpen
{
}
