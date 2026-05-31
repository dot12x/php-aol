<?php

declare(strict_types=1);

namespace Aol\Tests\Container;

use Aol\Aol;
use Aol\Attribute\Async;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        Aol::clearContainer();
    }

    #[Test]
    public function wrapResolvesFromContainerWhenAvailable(): void
    {
        $expected = new ContainerTarget(name: 'from-container');
        $container = new TestContainer([ContainerTarget::class => $expected]);
        Aol::useContainer($container);

        $w = Aol::wrap(ContainerTarget::class);
        $result = Aol::scope(fn () => $w->describe());

        self::assertSame('from-container', $result);
    }

    #[Test]
    public function wrapFallsBackToNewWhenContainerMissesClass(): void
    {
        $container = new TestContainer([]);
        Aol::useContainer($container);

        $w = Aol::wrap(ContainerTarget::class, name: 'from-args');
        $result = Aol::scope(fn () => $w->describe());

        self::assertSame('from-args', $result);
    }

    #[Test]
    public function wrapFallsBackToNewWhenNoContainerSet(): void
    {
        $w = Aol::wrap(ContainerTarget::class, name: 'plain');
        $result = Aol::scope(fn () => $w->describe());
        self::assertSame('plain', $result);
    }
}

class ContainerTarget
{
    public function __construct(public string $name = 'default')
    {
    }

    #[Async]
    public function describe(): string
    {
        return $this->name;
    }
}

final class TestContainer implements ContainerInterface
{
    /** @param array<class-string, object> $entries */
    public function __construct(private readonly array $entries)
    {
    }

    public function get(string $id): object
    {
        if (!isset($this->entries[$id])) {
            throw new class extends \Exception implements \Psr\Container\NotFoundExceptionInterface {};
        }
        return $this->entries[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }
}
