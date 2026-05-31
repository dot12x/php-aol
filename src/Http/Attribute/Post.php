<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Post
{
    public function __construct(public string $path)
    {
    }
}
