<?php

declare(strict_types=1);

namespace Aol\Internal;

use Aol\Attribute\Restart;
use Aol\Attribute\Retry;
use Aol\Process\Attribute\Process;
use Aol\Support\Arr;
use Aol\Support\Cast;
use Aol\WebSocket\Attribute\WebSocket as WsClient;

/**
 * @internal Reflection-derived facts about a wrappable class.
 *
 * Built once per class by ReflectionCache and reused for every
 * Aol::wrap() of that class.
 *
 * @template-covariant T of object
 */
final readonly class ClassMetadata
{
    /**
     * @param class-string<T> $className
     * @param array<string, bool> $asyncMethods         method name → true if marked #[Async]
     * @param list<string> $awakeMethods                methods marked #[OnAwake]
     * @param list<string> $sleepMethods                methods marked #[OnSleep]
     * @param array<string, int|float> $methodTimeouts  method name → seconds (only if #[Timeout])
     * @param array<string, Retry> $methodRetries       method name → Retry (only if #[Retry])
     * @param array<string, list<int|float>> $tickMethods    method name → list of `every` seconds (repeatable)
     * @param array<string, list<int>> $signalMethods        method name → list of signal ints (repeatable)
     * @param array<string, list<array{path: string, recursive: bool, pollInterval: float}>> $fileWatchMethods  method name → list of watch specs (repeatable)
     * @param list<string> $stdoutMethods   methods marked #[OnStdout]
     * @param list<string> $stderrMethods   methods marked #[OnStderr]
     * @param list<string> $exitMethods     methods marked #[OnExit]
     * @param array<string, list<string>> $sseMethods method name → list of #[OnSse] subscription URLs (repeatable)
     * @param list<string> $wsOpenMethods    methods marked #[Aol\WebSocket\Attribute\OnOpen]
     * @param list<string> $wsMessageMethods methods marked #[Aol\WebSocket\Attribute\OnMessage]
     * @param list<string> $wsCloseMethods   methods marked #[Aol\WebSocket\Attribute\OnClose]
     */
    public function __construct(
        public string $className,
        public int $poolSize,
        public int $queueCapacity,
        public array $asyncMethods,
        public array $awakeMethods = [],
        public array $sleepMethods = [],
        public array $methodTimeouts = [],
        public array $methodRetries = [],
        public ?Restart $restart = null,
        public array $tickMethods = [],
        public array $signalMethods = [],
        public array $fileWatchMethods = [],
        public ?Process $processSpec = null,
        public array $stdoutMethods = [],
        public array $stderrMethods = [],
        public array $exitMethods = [],
        public array $sseMethods = [],
        public ?WsClient $wsSpec = null,
        public ?string $wsConnectionProperty = null,
        public array $wsOpenMethods = [],
        public array $wsMessageMethods = [],
        public array $wsCloseMethods = [],
    ) {
    }

    public function isAsync(string $method): bool
    {
        return Cast::pick($this->asyncMethods, $method)->defaultValue(false)->toBool();
    }

    public function hasMethod(string $method): bool
    {
        return \array_key_exists($method, $this->asyncMethods);
    }

    public function timeoutFor(string $method): int|float|null
    {
        $v = Arr::from($this->methodTimeouts)->get($method);
        return \is_int($v) || \is_float($v) ? $v : null;
    }

    public function retryFor(string $method): ?Retry
    {
        return Cast::pick($this->methodRetries, $method)->toInstance(Retry::class);
    }
}
