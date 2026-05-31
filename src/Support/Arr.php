<?php

declare(strict_types=1);

namespace Aol\Support;

/**
 * Fluent array accessor.
 *
 * Arr::from($config)->path('db.host', 'localhost')
 * Arr::from($user)->get('name', 'anonymous')
 * Arr::from($items)->pluck('id')
 * Arr::from($items)->only(['id', 'name'])
 *
 * Immutable: each chained call returns a new wrapper (or the raw
 * value for leaf reads).
 */
final readonly class Arr
{
    /**
     * @param array<array-key, mixed> $items
     */
    private function __construct(private array $items)
    {
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public static function from(array $items): self
    {
        return new self($items);
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Wrap a key's value in a Cast for fluent coercion.
     *
     *   Arr::from($raw)->cast('size')->defaultValue(0)->toInt()
     */
    public function cast(string|int $key): Cast
    {
        return Cast::pick($this->items, $key);
    }

    /**
     * Dot-path read: Arr::from($cfg)->path('db.host', 'localhost').
     */
    public function path(string $path, mixed $default = null): mixed
    {
        if ($path === '') {
            return $default;
        }
        $current = self::traverse($this->items, $path);
        return $current === self::class ? $default : $current;
    }

    public function has(string|int $key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    public function hasPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        return self::traverse($this->items, $path) !== self::class;
    }

    /**
     * Walk dot-segments through $items; returns the leaf value or the
     * Arr::class string as a sentinel meaning "missing".
     *
     * @param array<array-key, mixed> $items
     */
    private static function traverse(array $items, string $path): mixed
    {
        $current = $items;
        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return self::class;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    public function first(mixed $default = null): mixed
    {
        if ($this->items === []) {
            return $default;
        }
        return \array_values($this->items)[0];
    }

    public function last(mixed $default = null): mixed
    {
        if ($this->items === []) {
            return $default;
        }
        $values = \array_values($this->items);
        return $values[\count($values) - 1];
    }

    public function isList(): bool
    {
        return \array_is_list($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Keep only the listed keys (order preserved). Missing keys
     * filled with $default.
     *
     * @param list<string|int> $keys
     */
    public function only(array $keys, mixed $default = null): self
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->items[$key] ?? $default;
        }
        return new self($out);
    }

    /**
     * @param list<string|int> $keys
     */
    public function except(array $keys): self
    {
        $copy = $this->items;
        foreach ($keys as $key) {
            unset($copy[$key]);
        }
        return new self($copy);
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string|int $key): array
    {
        $out = [];
        foreach ($this->items as $item) {
            if (\is_array($item) && \array_key_exists($key, $item)) {
                $out[] = $item[$key];
            } elseif (\is_object($item) && \is_string($key) && \property_exists($item, $key)) {
                $out[] = $item->{$key};
            }
        }
        return $out;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }
}
