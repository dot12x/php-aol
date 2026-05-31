<?php

declare(strict_types=1);

namespace Aol\PhpStan;

use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParameterReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\CallableType;
use PHPStan\Type\FloatType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VoidType;

/**
 * Utility helpers shared by all four extensions.
 */
final class ReflectionHelper
{
    /**
     * Convert a native ReflectionType to a PHPStan Type.
     * Falls back to MixedType for anything we cannot resolve.
     */
    public static function typeFromReflection(?\ReflectionType $ref): Type
    {
        if ($ref === null) {
            return new MixedType(true);
        }

        if ($ref instanceof \ReflectionNamedType) {
            $name = $ref->getName();

            if ($ref->isBuiltin()) {
                $base = match ($name) {
                    'int' => new IntegerType(),
                    'float' => new FloatType(),
                    'string' => new StringType(),
                    'bool' => new BooleanType(),
                    'array' => new ArrayType(new MixedType(), new MixedType()),
                    'void' => new VoidType(),
                    'never' => new NeverType(true),
                    'null' => new NullType(),
                    'mixed' => new MixedType(true),
                    'object' => new ObjectWithoutClassType(),
                    'callable' => new CallableType(),
                    'iterable' => new IterableType(new MixedType(), new MixedType()),
                    'static', 'self' => new MixedType(true),
                    default => new MixedType(true),
                };

                if ($ref->allowsNull() && !$base->isNull()->yes() && !($base instanceof MixedType)) {
                    return TypeCombinator::addNull($base);
                }
                return $base;
            }

            $type = new ObjectType($name);
            if ($ref->allowsNull()) {
                return TypeCombinator::addNull($type);
            }
            return $type;
        }

        if ($ref instanceof \ReflectionUnionType) {
            $types = array_map(
                static fn (\ReflectionType $t): Type => self::typeFromReflection($t),
                $ref->getTypes(),
            );
            return TypeCombinator::union(...$types);
        }

        if ($ref instanceof \ReflectionIntersectionType) {
            $types = array_map(
                static fn (\ReflectionType $t): Type => self::typeFromReflection($t),
                $ref->getTypes(),
            );
            return TypeCombinator::intersect(...$types);
        }

        return new MixedType(true);
    }

    /**
     * Return the public non-magic methods of $className using PHP native reflection.
     *
     * @param class-string $className
     * @return list<\ReflectionMethod>
     */
    public static function publicMethods(string $className): array
    {
        $rc = new \ReflectionClass($className);
        $result = [];
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if (str_starts_with($m->getName(), '__')) {
                continue;
            }
            $result[] = $m;
        }
        return $result;
    }

    /**
     * Return the public properties of $className using PHP native reflection.
     *
     * @param class-string $className
     * @return list<\ReflectionProperty>
     */
    public static function publicProperties(string $className): array
    {
        $rc = new \ReflectionClass($className);
        $result = [];
        foreach ($rc->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            $result[] = $p;
        }
        return $result;
    }

    /**
     * Given a ClassReflection for Wrapper<T> or Pending<T>, extract the PHPStan
     * Type for T from the active template type map.
     *
     * Returns null when the class is raw (no T bound).
     */
    public static function templateType(ClassReflection $cr, string $tParamName = 'T'): ?Type
    {
        $map = $cr->getActiveTemplateTypeMap();
        return $map->getType($tParamName);
    }

    /**
     * Given a ClassReflection for Wrapper<T> or Pending<T>, extract the concrete
     * class-string of T from the active template type map using PHPStan's own type
     * information (does NOT use PHP's class_exists()).
     *
     * Returns null when the class is raw or T is mixed/non-object.
     *
     * @return class-string|null
     */
    public static function templateClass(ClassReflection $cr, string $tParamName = 'T'): ?string
    {
        $type = self::templateType($cr, $tParamName);
        if ($type === null) {
            return null;
        }

        $names = $type->getObjectClassNames();
        if (count($names) === 0) {
            return null;
        }

        /** @var class-string $name */
        $name = $names[0];
        return $name;
    }

    /**
     * Check if T of the given wrapper/pending ClassReflection has the named method,
     * using PHPStan's own type system (so anonymous and inline-defined classes work).
     *
     * When T is mixed/unresolvable (empty objectClassNames), returns true so that
     * any method on a Pending<mixed> is allowed.
     */
    public static function templateHasMethod(ClassReflection $cr, string $methodName, string $tParamName = 'T'): bool
    {
        $type = self::templateType($cr, $tParamName);
        if ($type === null) {
            return false;
        }

        if (count($type->getObjectClassNames()) === 0) {
            return true;
        }

        return $type->hasMethod($methodName)->yes();
    }

    /**
     * Check if T of the given wrapper/pending ClassReflection has the named property,
     * using PHPStan's own type system.
     *
     * When T is mixed, returns true (any property allowed on Pending<mixed>).
     */
    public static function templateHasProperty(ClassReflection $cr, string $propertyName, string $tParamName = 'T'): bool
    {
        $type = self::templateType($cr, $tParamName);
        if ($type === null) {
            return false;
        }

        if (count($type->getObjectClassNames()) === 0) {
            return true;
        }

        return $type->hasProperty($propertyName)->yes();
    }

    /**
     * Get return type of a method on T using PHPStan's type system.
     * Falls back to MixedType when method cannot be found.
     */
    public static function templateMethodReturnType(ClassReflection $cr, string $methodName, string $tParamName = 'T'): Type
    {
        $type = self::templateType($cr, $tParamName);
        if ($type === null) {
            return new MixedType(true);
        }

        if (!$type->hasMethod($methodName)->yes()) {
            return new MixedType(true);
        }

        $method = $type->getMethod($methodName, new OutOfClassScope());
        $variants = $method->getVariants();
        if (count($variants) === 0) {
            return new MixedType(true);
        }

        return $variants[0]->getReturnType();
    }

    /**
     * Get return type of a property on T using PHPStan's type system.
     */
    public static function templatePropertyType(ClassReflection $cr, string $propertyName, string $tParamName = 'T'): Type
    {
        $type = self::templateType($cr, $tParamName);
        if ($type === null) {
            return new MixedType(true);
        }

        if (!$type->hasProperty($propertyName)->yes()) {
            return new MixedType(true);
        }

        $prop = $type->getProperty($propertyName, new OutOfClassScope());
        return $prop->getReadableType();
    }
}
