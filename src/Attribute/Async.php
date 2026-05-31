<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Mark a method as async. When called on a wrapped instance, the
 * method returns a Pending<ReturnType> instead of the real value;
 * the call is scheduled inside the active scope.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Async
{
}
