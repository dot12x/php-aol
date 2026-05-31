<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * A fabricated MethodReflection returned by our custom extensions.
 *
 * We construct one per projected method, giving it a name, declaring class,
 * parameter list, and return type.  Everything else is a safe no-op default.
 */
final class FabricatedMethodReflection implements MethodReflection
{
    /**
     * @param list<ParameterReflection> $parameters
     */
    public function __construct(
        private readonly ClassReflection $declaringClass,
        private readonly string $name,
        private readonly array $parameters,
        private readonly Type $returnType,
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
        return $this->name;
    }

    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    public function getVariants(): array
    {
        return [
            new FunctionVariant(
                templateTypeMap: TemplateTypeMap::createEmpty(),
                resolvedTemplateTypeMap: TemplateTypeMap::createEmpty(),
                parameters: $this->parameters,
                isVariadic: false,
                returnType: $this->returnType,
            ),
        ];
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
