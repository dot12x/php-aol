<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Lifecycle hook: called once per instance, immediately after the
 * wrapped class is instantiated by Aol::wrap(). All instances of a
 * pool wake in parallel. If any OnAwake throws, the entire wrap fails
 * with AolWrapException (after remaining waking instances finish).
 *
 * Method signature: () -> void. Sync. Synchronous return.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OnAwake
{
}
