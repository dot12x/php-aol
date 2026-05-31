<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Query
{
    public function __construct(public ?string $name = null)
    {
    }
}
