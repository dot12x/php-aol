<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Delete
{
    public function __construct(public string $path)
    {
    }
}
