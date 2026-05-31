<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Headers
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(public array $headers)
    {
    }
}
