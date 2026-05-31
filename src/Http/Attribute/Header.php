<?php

declare(strict_types=1);

namespace Aol\Http\Attribute;

/**
 * Method-level: static header on every call.
 * Parameter-level: header value sourced from the named parameter.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final readonly class Header
{
    public function __construct(public string $name, public ?string $value = null)
    {
    }
}
