<?php

declare(strict_types=1);

namespace Aol\Internal;

use Aol\Attribute\Async;
use Aol\Attribute\OnAwake;
use Aol\Attribute\OnSignal;
use Aol\Attribute\OnSleep;
use Aol\Attribute\OnTick;
use Aol\Attribute\Restart;
use Aol\Attribute\Retry;
use Aol\Attribute\Timeout;
use Aol\Attribute\Worker;
use Aol\File\Attribute\OnFileChange;
use Aol\Http\Attribute\OnSse;
use Aol\Process\Attribute\OnExit;
use Aol\Process\Attribute\OnStderr;
use Aol\Process\Attribute\OnStdout;
use Aol\Process\Attribute\Process as ProcessAttr;
use Aol\WebSocket\Attribute\OnClose as WsOnClose;
use Aol\WebSocket\Attribute\OnMessage as WsOnMessage;
use Aol\WebSocket\Attribute\OnOpen as WsOnOpen;
use Aol\WebSocket\Attribute\WebSocket as WsClient;
use Aol\WebSocket\Attribute\WsConnection;

/**
 * @internal Process-lifetime cache of ClassMetadata. The first
 * Aol::wrap() of a class pays the reflection cost (~5ms typical);
 * subsequent wraps hit the cache (<1ms).
 */
final class ReflectionCache
{
    /** @var array<class-string, ClassMetadata<object>> */
    private static array $cache = [];

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return ClassMetadata<T>
     */
    public static function for(string $class): ClassMetadata
    {
        if (!isset(self::$cache[$class])) {
            self::$cache[$class] = self::analyze($class);
        }
        /** @var ClassMetadata<T> */
        return self::$cache[$class];
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return ClassMetadata<T>
     */
    private static function analyze(string $class): ClassMetadata
    {
        $reflection = new \ReflectionClass($class);

        $poolSize = 1;
        $queueCap = 1024;
        $workerAttrs = $reflection->getAttributes(Worker::class);
        if (\count($workerAttrs) > 0) {
            $worker = $workerAttrs[0]->newInstance();
            $poolSize = $worker->pool;
            $queueCap = $worker->queue;
        }

        $restart = null;
        $restartAttrs = $reflection->getAttributes(Restart::class);
        if (\count($restartAttrs) > 0) {
            $restart = $restartAttrs[0]->newInstance();
        }

        $processSpec = null;
        $processAttrs = $reflection->getAttributes(ProcessAttr::class);
        if (\count($processAttrs) > 0) {
            $processSpec = $processAttrs[0]->newInstance();
        }

        $wsSpec = null;
        $wsAttrs = $reflection->getAttributes(WsClient::class);
        if (\count($wsAttrs) > 0) {
            $wsSpec = $wsAttrs[0]->newInstance();
        }

        $wsConnectionProperty = null;
        foreach ($reflection->getProperties() as $prop) {
            if (\count($prop->getAttributes(WsConnection::class)) > 0) {
                $wsConnectionProperty = $prop->getName();
                break;
            }
        }

        $asyncMethods = [];
        $awakeMethods = [];
        $sleepMethods = [];
        $methodTimeouts = [];
        $methodRetries = [];
        $tickMethods = [];
        $signalMethods = [];
        $fileWatchMethods = [];
        $stdoutMethods = [];
        $stderrMethods = [];
        $exitMethods = [];
        $sseMethods = [];
        $wsOpenMethods = [];
        $wsMessageMethods = [];
        $wsCloseMethods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor() || $method->isDestructor()) {
                continue;
            }
            if (\str_starts_with($method->getName(), '__')) {
                continue;
            }
            $name = $method->getName();

            $asyncMethods[$name] = \count($method->getAttributes(Async::class)) > 0;

            if (\count($method->getAttributes(OnAwake::class)) > 0) {
                $awakeMethods[] = $name;
            }
            if (\count($method->getAttributes(OnSleep::class)) > 0) {
                $sleepMethods[] = $name;
            }
            $timeoutAttrs = $method->getAttributes(Timeout::class);
            if (\count($timeoutAttrs) > 0) {
                $methodTimeouts[$name] = $timeoutAttrs[0]->newInstance()->seconds;
            }
            $retryAttrs = $method->getAttributes(Retry::class);
            if (\count($retryAttrs) > 0) {
                $methodRetries[$name] = $retryAttrs[0]->newInstance();
            }
            $tickAttrs = $method->getAttributes(OnTick::class);
            if (\count($tickAttrs) > 0) {
                $tickMethods[$name] = \array_map(
                    static fn ($a) => $a->newInstance()->every,
                    $tickAttrs,
                );
            }
            $signalAttrs = $method->getAttributes(OnSignal::class);
            if (\count($signalAttrs) > 0) {
                $signalMethods[$name] = \array_map(
                    static fn ($a) => $a->newInstance()->signal,
                    $signalAttrs,
                );
            }
            $watchAttrs = $method->getAttributes(OnFileChange::class);
            if (\count($watchAttrs) > 0) {
                $fileWatchMethods[$name] = \array_map(
                    static function ($a): array {
                        $i = $a->newInstance();
                        return ['path' => $i->path, 'recursive' => $i->recursive, 'pollInterval' => $i->pollInterval];
                    },
                    $watchAttrs,
                );
            }
            if (\count($method->getAttributes(OnStdout::class)) > 0) {
                $stdoutMethods[] = $name;
            }
            if (\count($method->getAttributes(OnStderr::class)) > 0) {
                $stderrMethods[] = $name;
            }
            if (\count($method->getAttributes(OnExit::class)) > 0) {
                $exitMethods[] = $name;
            }
            $sseAttrs = $method->getAttributes(OnSse::class);
            if (\count($sseAttrs) > 0) {
                $sseMethods[$name] = \array_map(
                    static fn ($a) => $a->newInstance()->url,
                    $sseAttrs,
                );
            }
            if (\count($method->getAttributes(WsOnOpen::class)) > 0) {
                $wsOpenMethods[] = $name;
            }
            if (\count($method->getAttributes(WsOnMessage::class)) > 0) {
                $wsMessageMethods[] = $name;
            }
            if (\count($method->getAttributes(WsOnClose::class)) > 0) {
                $wsCloseMethods[] = $name;
            }
        }

        return new ClassMetadata(
            className: $class,
            poolSize: $poolSize,
            queueCapacity: $queueCap,
            asyncMethods: $asyncMethods,
            awakeMethods: $awakeMethods,
            sleepMethods: $sleepMethods,
            methodTimeouts: $methodTimeouts,
            methodRetries: $methodRetries,
            restart: $restart,
            tickMethods: $tickMethods,
            signalMethods: $signalMethods,
            fileWatchMethods: $fileWatchMethods,
            processSpec: $processSpec,
            stdoutMethods: $stdoutMethods,
            stderrMethods: $stderrMethods,
            exitMethods: $exitMethods,
            sseMethods: $sseMethods,
            wsSpec: $wsSpec,
            wsConnectionProperty: $wsConnectionProperty,
            wsOpenMethods: $wsOpenMethods,
            wsMessageMethods: $wsMessageMethods,
            wsCloseMethods: $wsCloseMethods,
        );
    }

    /**
     * @internal Test-only: clear cache between tests if needed.
     */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
