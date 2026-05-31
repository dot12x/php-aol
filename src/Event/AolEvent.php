<?php

declare(strict_types=1);

namespace Aol\Event;

/**
 * Base class for every lifecycle event the library emits.
 *
 * Events are dispatched synchronously to listeners registered via
 * Aol::onEvent(). Listeners that need to do slow work should wrap
 * themselves in Aol::async() inside an open scope.
 *
 * Listener exceptions are caught and (if a PSR-3 logger is set via
 * Aol::useLogger()) logged at error level — they never bubble back
 * into the library's runtime.
 */
abstract readonly class AolEvent
{
    public function __construct(public float $at)
    {
    }
}
