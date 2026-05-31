<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;

/**
 * Teaches PHPStan that Pending<T> has all public methods of T.
 *
 * Every projected method returns Pending<OriginalReturnType> because chaining
 * a method on a Pending value produces another Pending.
 *
 * When T is mixed (e.g. closures returning anonymous classes), any method is
 * accepted and returns Pending<mixed>.
 */
final class PendingMethodsExtension implements MethodsClassReflectionExtension
{
    private const PENDING_CLASS = 'Aol\Pending';

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (!$classReflection->is(self::PENDING_CLASS)) {
            return false;
        }

        return ReflectionHelper::templateHasMethod($classReflection, $methodName);
    }

    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $type = ReflectionHelper::templateType($classReflection);

        if ($type !== null && $type->hasMethod($methodName)->yes()) {
            $innerMethod = $type->getMethod($methodName, new OutOfClassScope());
            $innerReturn = $innerMethod->getVariants()[0]->getReturnType();
            $returnType = new GenericObjectType(self::PENDING_CLASS, [$innerReturn]);

            return new DelegatingMethodReflection(
                declaringClass: $classReflection,
                inner: $innerMethod,
                overrideReturnType: $returnType,
            );
        }

        return new FabricatedMethodReflection(
            declaringClass: $classReflection,
            name: $methodName,
            parameters: [],
            returnType: new GenericObjectType(self::PENDING_CLASS, [new MixedType(true)]),
        );
    }
}
