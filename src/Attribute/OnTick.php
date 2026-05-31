<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Periodic tick. Method runs every $every seconds while the wrap is
 * alive in its owning scope. Ticks are background tasks — they don't
 * block the scope from closing.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class OnTick
{
    public function __construct(public int|float $every)
    {
        if ($every <= 0) {
            throw new \InvalidArgumentException('OnTick every must be > 0.');
        }
    }
}
