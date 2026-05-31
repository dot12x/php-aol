<?php

declare(strict_types=1);

namespace Aol\Tests\Event;

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Attribute\OnAwake;
use Aol\Attribute\OnSleep;
use Aol\Attribute\Restart;
use Aol\Attribute\Retry;
use Aol\Event\AolEvent;
use Aol\Event\Awake;
use Aol\Event\Crash;
use Aol\Event\Restart as RestartEvent;
use Aol\Event\RetryAttempted;
use Aol\Event\Sleep;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class EventTest extends TestCase
{
    /** @var list<AolEvent> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->events = [];
        Aol::onEvent(function (AolEvent $e): void {
            $this->events[] = $e;
        });
    }

    protected function tearDown(): void
    {
        Aol::clearEventListeners();
        Aol::clearLogger();
        EventTarget::reset();
    }

    #[Test]
    public function awakeEmittedOnWrap(): void
    {
        Aol::wrap(EventTarget::class);

        $awakes = $this->only(Awake::class);
        self::assertCount(1, $awakes);
        self::assertSame(EventTarget::class, $awakes[0]->className);
        self::assertSame(1, $awakes[0]->poolSize);
    }

    #[Test]
    public function sleepEmittedAtScopeClose(): void
    {
        Aol::scope(function () {
            Aol::wrap(EventTarget::class);
        });

        $sleeps = $this->only(Sleep::class);
        self::assertCount(1, $sleeps);
        self::assertSame(EventTarget::class, $sleeps[0]->className);
    }

    #[Test]
    public function crashEmittedOnMethodFailure(): void
    {
        $w = Aol::wrap(EventTarget::class);
        try {
            Aol::scope(fn () => $w->boom());
        } catch (\RuntimeException) {
        }

        $crashes = $this->only(Crash::class);
        self::assertCount(1, $crashes);
        self::assertSame('boom', $crashes[0]->method);
        self::assertInstanceOf(\RuntimeException::class, $crashes[0]->error);
    }

    #[Test]
    public function retryAttemptedEmittedPerRetry(): void
    {
        EventTarget::$flakyFailsLeft = 2;   // 2 failures, then success
        $w = Aol::wrap(EventTarget::class);

        Aol::scope(fn () => $w->flaky());

        $retries = $this->only(RetryAttempted::class);
        self::assertCount(2, $retries);
        self::assertSame(1, $retries[0]->attempt);
        self::assertSame(2, $retries[1]->attempt);
        self::assertSame('flaky', $retries[0]->method);
    }

    #[Test]
    public function restartEmittedOnInstanceReplace(): void
    {
        $w = Aol::wrap(EventTarget::class);
        try {
            Aol::scope(fn () => $w->boom());
        } catch (\RuntimeException) {
        }

        $restarts = $this->only(RestartEvent::class);
        self::assertCount(1, $restarts);
        self::assertSame(0, $restarts[0]->instanceIndex);
        self::assertInstanceOf(\RuntimeException::class, $restarts[0]->cause);
    }

    #[Test]
    public function listenerExceptionDoesNotBreakRuntime(): void
    {
        Aol::clearEventListeners();
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message];
            }
        };
        Aol::useLogger($logger);
        Aol::onEvent(static function (AolEvent $e): void {
            throw new \RuntimeException('listener bug');
        });

        // Should not throw out of the wrap.
        Aol::wrap(EventTarget::class);

        self::assertNotEmpty($logger->records);
        self::assertSame('error', $logger->records[0]['level']);
    }

    /**
     * @template T of AolEvent
     * @param class-string<T> $class
     * @return list<T>
     */
    private function only(string $class): array
    {
        return \array_values(\array_filter($this->events, static fn (AolEvent $e): bool => $e instanceof $class));
    }
}

#[Restart(max: 5, within: 60)]
class EventTarget
{
    public static int $flakyFailsLeft = 0;

    public static function reset(): void
    {
        self::$flakyFailsLeft = 0;
    }

    #[OnAwake]
    public function init(): void
    {
    }

    #[OnSleep]
    public function cleanup(): void
    {
    }

    #[Async]
    public function boom(): string
    {
        throw new \RuntimeException('boom');
    }

    #[Async]
    #[Retry(times: 3, on: [\RuntimeException::class])]
    public function flaky(): string
    {
        if (self::$flakyFailsLeft > 0) {
            self::$flakyFailsLeft--;
            throw new \RuntimeException('flake');
        }
        return 'ok';
    }
}
