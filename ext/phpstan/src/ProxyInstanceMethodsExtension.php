<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\MixedType;

/**
 * Teaches PHPStan that ProxyInstance<T> has all public methods of T.
 *
 * ProxyInstance is a runtime dispatcher for declarative HTTP interfaces.
 * It stores the interface class in $interface and dispatches calls via __call.
 * This extension projects every method of T onto ProxyInstance so callers can
 * call $proxy->someMethod(...) without method.notFound errors.
 */
final class ProxyInstanceMethodsExtension implements MethodsClassReflectionExtension
{
    private const PROXY_CLASS = 'Aol\Internal\Http\ProxyInstance';

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (!$classReflection->is(self::PROXY_CLASS)) {
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

            return new DelegatingMethodReflection(
                declaringClass: $classReflection,
                inner: $innerMethod,
                overrideReturnType: $innerReturn,
            );
        }

        return new FabricatedMethodReflection(
            declaringClass: $classReflection,
            name: $methodName,
            parameters: [],
            returnType: new MixedType(true),
        );
    }
}
