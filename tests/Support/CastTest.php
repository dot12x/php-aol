<?php

declare(strict_types=1);

namespace Aol\Tests\Support;

use Aol\Support\Cast;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CastTest extends TestCase
{
    #[Test]
    public function castsToInt(): void
    {
        self::assertSame(42, Cast::from(42)->toInt());
        self::assertSame(42, Cast::from('42')->toInt());
        self::assertSame(42, Cast::from(42.7)->toInt());
        self::assertSame(0, Cast::from('xyz')->toInt());
        self::assertSame(99, Cast::from(null)->toInt(99));
        self::assertSame(7, Cast::from([])->toInt(7));
    }

    #[Test]
    public function castsToFloat(): void
    {
        self::assertSame(1.5, Cast::from(1.5)->toFloat());
        self::assertSame(1.5, Cast::from('1.5')->toFloat());
        self::assertSame(0.0, Cast::from('xyz')->toFloat());
        self::assertSame(2.5, Cast::from(null)->toFloat(2.5));
    }

    #[Test]
    public function castsToString(): void
    {
        self::assertSame('hello', Cast::from('hello')->toString());
        self::assertSame('42', Cast::from(42)->toString());
        self::assertSame('true', Cast::from(true)->toString());
        self::assertSame('false', Cast::from(false)->toString());
        self::assertSame('fallback', Cast::from([])->toString('fallback'));

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'from-object';
            }
        };
        self::assertSame('from-object', Cast::from($stringable)->toString());
    }

    #[Test]
    public function castsToBool(): void
    {
        self::assertTrue(Cast::from(true)->toBool());
        self::assertFalse(Cast::from(false)->toBool());
        self::assertTrue(Cast::from('yes')->toBool());
        self::assertFalse(Cast::from('no')->toBool());
        self::assertTrue(Cast::from('1')->toBool());
        self::assertFalse(Cast::from('')->toBool());
        self::assertTrue(Cast::from('???')->toBool(true));
        self::assertFalse(Cast::from(0)->toBool());
        self::assertTrue(Cast::from(1)->toBool());
    }

    #[Test]
    public function toInstanceMatchesClass(): void
    {
        $obj = new \stdClass();
        self::assertSame($obj, Cast::from($obj)->toInstance(\stdClass::class));
        self::assertNull(Cast::from('not an obj')->toInstance(\stdClass::class));
        self::assertNull(Cast::from($obj)->toInstance(\Exception::class));
    }

    #[Test]
    public function toArrayOrNull(): void
    {
        self::assertSame(['a', 'b'], Cast::from(['a', 'b'])->toArrayOrNull());
        self::assertNull(Cast::from('x')->toArrayOrNull());
    }

    #[Test]
    public function pickReadsKeyWithoutNullCoalesce(): void
    {
        $raw = ['size' => 1024, 'name' => 'config.txt'];
        self::assertSame(1024, Cast::pick($raw, 'size')->defaultValue(0)->toInt());
        self::assertSame('config.txt', Cast::pick($raw, 'name')->defaultValue('?')->toString());
        // Missing key falls back through Cast's default — no `?? null` at the call site.
        self::assertSame(0, Cast::pick($raw, 'missing')->defaultValue(0)->toInt());
        self::assertSame('fallback', Cast::pick($raw, 'missing')->defaultValue('fallback')->toString());
    }

    #[Test]
    public function defaultValueChainedBeatsMethodArg(): void
    {
        self::assertSame(0, Cast::from(null)->defaultValue(0)->toInt(42));
        self::assertSame('a', Cast::from(null)->defaultValue('a')->toString('b'));
    }
}
