<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\MixedType;

/**
 * Teaches PHPStan that Pending<T> exposes all public properties of T.
 *
 * Accessing $pending->someProp on a Pending<T> that has someProp returns
 * Pending<PropertyType> via magic __get chaining.
 *
 * When T is mixed, any property is accepted and returns Pending<mixed>.
 */
final class PendingPropertiesExtension implements PropertiesClassReflectionExtension
{
    private const PENDING_CLASS = 'Aol\Pending';

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        if (!$classReflection->is(self::PENDING_CLASS)) {
            return false;
        }

        return ReflectionHelper::templateHasProperty($classReflection, $propertyName);
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $type = ReflectionHelper::templateType($classReflection);

        if ($type !== null && $type->hasProperty($propertyName)->yes()) {
            $innerProp = $type->getProperty($propertyName, new OutOfClassScope());
            $innerType = $innerProp->getReadableType();
            $returnType = new GenericObjectType(self::PENDING_CLASS, [$innerType]);

            return new FabricatedPropertyReflection(
                declaringClass: $classReflection,
                readType: $returnType,
            );
        }

        return new FabricatedPropertyReflection(
            declaringClass: $classReflection,
            readType: new GenericObjectType(self::PENDING_CLASS, [new MixedType(true)]),
        );
    }
}
