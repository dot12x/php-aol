<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Path
{
    public function __construct(public ?string $name = null)
    {
    }
}
