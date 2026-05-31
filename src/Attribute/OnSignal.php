<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * UNIX signal handler. Method runs when the named signal arrives.
 * Requires ext-pcntl; silently no-op on Windows / without pcntl.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class OnSignal
{
    public function __construct(public int $signal)
    {
    }
}
