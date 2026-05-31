<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

/**
 * Marks a method on a declarative HTTP interface as a Server-Sent Events
 * stream. The method's return type must be `iterable` (or
 * `Aol\Http\Sse\SseStream`); the proxy returns an SseStream that yields
 * `Aol\Http\Sse\SseEvent` instances.
 *
 *     #[Get('/ticks')]
 *     #[SseStream]
 *     public function ticks(#[Query] string $symbol): iterable;
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class SseStream
{
}
