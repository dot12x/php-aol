<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BaseUrl
{
    public function __construct(public string $url)
    {
    }
}
