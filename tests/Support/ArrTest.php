<?php

declare(strict_types=1);

namespace Aol\Tests\Support;

use Aol\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    #[Test]
    public function getWithDefault(): void
    {
        $a = Arr::from(['x' => 1, 'y' => 2]);
        self::assertSame(1, $a->get('x'));
        self::assertNull($a->get('z'));
        self::assertSame('fallback', $a->get('z', 'fallback'));
    }

    #[Test]
    public function pathTraversesNested(): void
    {
        $cfg = Arr::from(['db' => ['host' => 'localhost', 'port' => 5432]]);
        self::assertSame('localhost', $cfg->path('db.host'));
        self::assertSame(5432, $cfg->path('db.port'));
        self::assertNull($cfg->path('db.missing'));
        self::assertSame('def', $cfg->path('cache.tier', 'def'));
        self::assertNull(Arr::from([])->path(''));
    }

    #[Test]
    public function hasAndHasPath(): void
    {
        $a = Arr::from(['x' => null, 'y' => ['z' => 1]]);
        self::assertTrue($a->has('x'));
        self::assertFalse($a->has('missing'));
        self::assertTrue($a->hasPath('y.z'));
        self::assertFalse($a->hasPath('y.missing'));
    }

    #[Test]
    public function firstLast(): void
    {
        $a = Arr::from(['a', 'b', 'c']);
        self::assertSame('a', $a->first());
        self::assertSame('c', $a->last());
        self::assertSame('def', Arr::from([])->first('def'));
        self::assertSame('def', Arr::from([])->last('def'));
    }

    #[Test]
    public function onlyAndExceptReturnNewWrappers(): void
    {
        $a = Arr::from(['x' => 1, 'y' => 2, 'z' => 3]);
        self::assertSame(['x' => 1, 'z' => 3], $a->only(['x', 'z'])->toArray());
        self::assertSame(['y' => 2], $a->except(['x', 'z'])->toArray());
        // original untouched
        self::assertSame(['x' => 1, 'y' => 2, 'z' => 3], $a->toArray());
    }

    #[Test]
    public function pluckExtractsField(): void
    {
        $users = Arr::from([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 3, 'name' => 'c'],
        ]);
        self::assertSame([1, 2, 3], $users->pluck('id'));
        self::assertSame(['a', 'b', 'c'], $users->pluck('name'));
    }

    #[Test]
    public function isListCountIsEmpty(): void
    {
        self::assertTrue(Arr::from(['a', 'b'])->isList());
        self::assertFalse(Arr::from(['x' => 1])->isList());
        self::assertSame(3, Arr::from([1, 2, 3])->count());
        self::assertTrue(Arr::from([])->isEmpty());
        self::assertFalse(Arr::from([1])->isEmpty());
    }

    #[Test]
    public function castIntegratesWithFluentCoercion(): void
    {
        $raw = ['port' => '8080', 'host' => 'localhost'];
        $bag = Arr::from($raw);

        self::assertSame(8080, $bag->cast('port')->defaultValue(80)->toInt());
        self::assertSame('localhost', $bag->cast('host')->defaultValue('?')->toString());
        // Missing key — Cast default kicks in.
        self::assertSame(80, $bag->cast('missing')->defaultValue(80)->toInt());
    }
}
