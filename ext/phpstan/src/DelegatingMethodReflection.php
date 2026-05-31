<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Reflection\ParametersAcceptor;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * A MethodReflection that delegates structure to an existing ExtendedMethodReflection
 * but overrides the return type.
 *
 * Used to project T's methods onto Wrapper<T>/Pending<T>/ProxyInstance<T> while
 * preserving the original parameter signatures (including generic types, optionality, etc).
 */
final class DelegatingMethodReflection implements MethodReflection
{
    public function __construct(
        private readonly ClassReflection $declaringClass,
        private readonly MethodReflection $inner,
        private readonly Type $overrideReturnType,
    ) {
    }

    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    public function isStatic(): bool
    {
        return false;
    }

    public function isPrivate(): bool
    {
        return false;
    }

    public function isPublic(): bool
    {
        return true;
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        $result = [];
        foreach ($this->inner->getVariants() as $variant) {
            $result[] = new FunctionVariant(
                templateTypeMap: TemplateTypeMap::createEmpty(),
                resolvedTemplateTypeMap: TemplateTypeMap::createEmpty(),
                parameters: $variant->getParameters(),
                isVariadic: $variant->isVariadic(),
                returnType: $this->overrideReturnType,
            );
        }

        if (count($result) === 0) {
            $result[] = new FunctionVariant(
                templateTypeMap: TemplateTypeMap::createEmpty(),
                resolvedTemplateTypeMap: TemplateTypeMap::createEmpty(),
                parameters: [],
                isVariadic: false,
                returnType: $this->overrideReturnType,
            );
        }

        return $result;
    }

    public function isDeprecated(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getDeprecatedDescription(): ?string
    {
        return null;
    }

    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    public function getThrowType(): ?Type
    {
        return null;
    }

    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createMaybe();
    }
}
