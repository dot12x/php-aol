<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\PassedByReference;
use PHPStan\Type\Type;

/**
 * Wraps an existing ParameterReflection and overrides its type.
 *
 * Used by WrapperMethodsExtension to widen parameter types to
 * X|Pending<mixed> for auto-graph semantics.
 */
final class WideningParameterReflection implements ParameterReflection
{
    public function __construct(
        private readonly ParameterReflection $inner,
        private readonly Type $widenedType,
    ) {
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function isOptional(): bool
    {
        return $this->inner->isOptional();
    }

    public function getType(): Type
    {
        return $this->widenedType;
    }

    public function passedByReference(): PassedByReference
    {
        return $this->inner->passedByReference();
    }

    public function isVariadic(): bool
    {
        return $this->inner->isVariadic();
    }

    public function getDefaultValue(): ?Type
    {
        return $this->inner->getDefaultValue();
    }
}
