<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

/**
 * Subscribes a method on a wrapped class to a Server-Sent Events stream.
 *
 * The method is invoked once per event the server sends. The signature
 * must accept a single Aol\Http\Sse\SseEvent argument. Multiple #[OnSse]
 * methods per class are allowed, and the attribute itself is repeatable
 * so a single method may subscribe to several streams.
 *
 *     #[OnSse('https://api.example.com/events')]
 *     public function rx(SseEvent $event): void { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class OnSse
{
    public function __construct(public string $url)
    {
    }
}
