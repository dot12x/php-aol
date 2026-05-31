<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Teaches PHPStan that Wrapper<T> has all public methods of T.
 *
 * For methods marked #[Aol\Attribute\Async] the return type becomes
 * Pending<OriginalReturnType>.  For sync methods the declared return type is
 * passed through unchanged.
 *
 * Parameter types are widened to X|Pending<mixed> to allow callers to pass any
 * Pending value where X is expected (auto-graph semantics).
 */
final class WrapperMethodsExtension implements MethodsClassReflectionExtension
{
    private const WRAPPER_CLASS = 'Aol\Internal\Wrapper';
    private const ASYNC_ATTR = 'Aol\Attribute\Async';
    private const PENDING_CLASS = 'Aol\Pending';

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (!$classReflection->is(self::WRAPPER_CLASS)) {
            return false;
        }

        return ReflectionHelper::templateHasMethod($classReflection, $methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $type = ReflectionHelper::templateType($classReflection);
        if ($type === null || !$type->hasMethod($methodName)->yes()) {
            return new FabricatedMethodReflection(
                declaringClass: $classReflection,
                name: $methodName,
                parameters: [],
                returnType: new MixedType(true),
            );
        }

        $innerMethod = $type->getMethod($methodName, new OutOfClassScope());
        $isAsync = $this->methodIsAsync($innerMethod);
        $innerReturn = $innerMethod->getVariants()[0]->getReturnType();
        $returnType = $isAsync
            ? new GenericObjectType(self::PENDING_CLASS, [$innerReturn])
            : $innerReturn;

        return $this->buildWithWidenedParams($classReflection, $innerMethod, $returnType);
    }

    /**
     * Check if the named method has #[Aol\Attribute\Async] by inspecting
     * PHPStan's own AttributeReflection objects (works even for classes not
     * loadable via PHP's autoloader at analysis time).
     */
    private function methodIsAsync(MethodReflection $method): bool
    {
        if (!($method instanceof ExtendedMethodReflection)) {
            return false;
        }

        foreach ($method->getAttributes() as $attr) {
            if ($attr->getName() === self::ASYNC_ATTR) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a FunctionVariant that copies parameters from the inner method but
     * widens each type to X|Pending<mixed> (accepts any Pending for auto-graph).
     */
    private function buildWithWidenedParams(
        ClassReflection $declaringClass,
        MethodReflection $inner,
        Type $returnType,
    ): MethodReflection {
        $innerVariants = $inner->getVariants();
        if (count($innerVariants) === 0) {
            return new FabricatedMethodReflection(
                declaringClass: $declaringClass,
                name: $inner->getName(),
                parameters: [],
                returnType: $returnType,
            );
        }

        $innerVariant = $innerVariants[0];
        $pendingMixed = new GenericObjectType(self::PENDING_CLASS, [new MixedType(true)]);
        $widenedParams = [];
        foreach ($innerVariant->getParameters() as $p) {
            $origType = $p->getType();
            $widenedType = TypeCombinator::union($origType, $pendingMixed);
            $widenedParams[] = new WideningParameterReflection($p, $widenedType);
        }

        return new FabricatedMethodReflection(
            declaringClass: $declaringClass,
            name: $inner->getName(),
            parameters: $widenedParams,
            returnType: $returnType,
        );
    }
}
