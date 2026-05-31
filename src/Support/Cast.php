<?php

declare(strict_types=1);

namespace Aol\Support;

/**
 * Fluent value coercion.
 *
 *   Cast::from($mixed)->toInt(0)
 *   Cast::from($mixed)->defaultValue(0)->toInt()
 *   Cast::from($mixed)->defaultValue('anon')->toString()
 *
 * defaultValue() sets a fallback used by the next `to*()` call when
 * the source can't be coerced. Each `to*()` method also accepts an
 * inline default — the chained one wins when both are present.
 */
final readonly class Cast
{
    private function __construct(
        private mixed $value,
        private mixed $default = null,
        private bool $hasDefault = false,
    ) {
    }

    public static function from(mixed $value): self
    {
        return new self($value);
    }

    /**
     * Pick a key from an array safely — no `?? null` at the call site.
     *
     *   Cast::pick($raw, 'size')->defaultValue(0)->toInt()
     *
     * @param array<array-key, mixed> $arr
     */
    public static function pick(array $arr, string|int $key): self
    {
        return new self($arr[$key] ?? null);
    }

    public function defaultValue(mixed $default): self
    {
        return new self($this->value, $default, true);
    }

    public function toInt(int $default = 0): int
    {
        $v = $this->value;
        if (\is_int($v)) {
            return $v;
        }
        if (\is_float($v)) {
            return (int) $v;
        }
        if (\is_string($v) && \is_numeric($v)) {
            return (int) $v;
        }
        return $this->intDefault($default);
    }

    public function toFloat(float $default = 0.0): float
    {
        $v = $this->value;
        if (\is_int($v) || \is_float($v)) {
            return (float) $v;
        }
        if (\is_string($v) && \is_numeric($v)) {
            return (float) $v;
        }
        return $this->floatDefault($default);
    }

    public function toString(string $default = ''): string
    {
        $v = $this->value;
        if (\is_string($v)) {
            return $v;
        }
        if (\is_int($v) || \is_float($v)) {
            return (string) $v;
        }
        if (\is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if ($v instanceof \Stringable) {
            return (string) $v;
        }
        return $this->stringDefault($default);
    }

    public function toBool(bool $default = false): bool
    {
        $v = $this->value;
        if (\is_bool($v)) {
            return $v;
        }
        if (\is_int($v) || \is_float($v)) {
            return $v !== 0 && $v !== 0.0;
        }
        if (\is_string($v)) {
            $matched = match (\strtolower($v)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off', '' => false,
                default => null,
            };
            if ($matched !== null) {
                return $matched;
            }
        }
        return $this->boolDefault($default);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function toInstance(string $class): ?object
    {
        return $this->value instanceof $class ? $this->value : null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function toArrayOrNull(): ?array
    {
        return \is_array($this->value) ? $this->value : null;
    }

    private function intDefault(int $methodDefault): int
    {
        if (!$this->hasDefault) {
            return $methodDefault;
        }
        $d = $this->default;
        if (\is_int($d)) {
            return $d;
        }
        if (\is_float($d)) {
            return (int) $d;
        }
        if (\is_string($d) && \is_numeric($d)) {
            return (int) $d;
        }
        return $methodDefault;
    }

    private function floatDefault(float $methodDefault): float
    {
        if (!$this->hasDefault) {
            return $methodDefault;
        }
        $d = $this->default;
        if (\is_int($d) || \is_float($d)) {
            return (float) $d;
        }
        if (\is_string($d) && \is_numeric($d)) {
            return (float) $d;
        }
        return $methodDefault;
    }

    private function stringDefault(string $methodDefault): string
    {
        if (!$this->hasDefault) {
            return $methodDefault;
        }
        $d = $this->default;
        if (\is_string($d)) {
            return $d;
        }
        if (\is_int($d) || \is_float($d)) {
            return (string) $d;
        }
        if (\is_bool($d)) {
            return $d ? 'true' : 'false';
        }
        return $methodDefault;
    }

    private function boolDefault(bool $methodDefault): bool
    {
        if (!$this->hasDefault) {
            return $methodDefault;
        }
        $d = $this->default;
        if (\is_bool($d)) {
            return $d;
        }
        return $methodDefault;
    }
}
