<?php

declare(strict_types=1);

namespace Tests\Process;

use Aol\Aol;
use Aol\Process\Attribute\OnExit;
use Aol\Process\Attribute\OnStdout;
use Aol\Process\Attribute\Process as ProcessAttr;
use Aol\Time;
use PHPUnit\Framework\TestCase;

final class DeclarativeProcessTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\is_executable('/bin/sh')) {
            self::markTestSkipped('/bin/sh required');
        }
        ChildFixture::$lines = [];
        ChildFixture::$exitCode = null;
        ChildFixture::$exited = 0;
        RestartFixture::$exits = 0;
    }

    public function testDeclarativeChildStreamsStdoutAndFiresOnExit(): void
    {
        Aol::scope(function () {
            $w = Aol::wrap(ChildFixture::class);
            Time::sleep(0.4);
            $_ = $w;
        });

        self::assertSame(['one', 'two', 'three'], ChildFixture::$lines);
        self::assertSame(0, ChildFixture::$exitCode);
        self::assertSame(1, ChildFixture::$exited, 'OnExit fires exactly once for a non-restart child');
    }

    public function testRestartSpawnsAgainOnExit(): void
    {
        Aol::scope(function () {
            $w = Aol::wrap(RestartFixture::class);
            Time::sleep(0.45);
            $_ = $w;
        });

        self::assertGreaterThanOrEqual(2, RestartFixture::$exits, 'OnExit fires for each child generation');
    }
}

#[ProcessAttr(command: "/bin/sh -c 'echo one; echo two; echo three'")]
final class ChildFixture
{
    /** @var list<string> */
    public static array $lines = [];
    public static ?int $exitCode = null;
    public static int $exited = 0;

    #[OnStdout]
    public function onLine(string $line): void
    {
        self::$lines[] = $line;
    }

    #[OnExit]
    public function onExit(int $code): void
    {
        self::$exitCode = $code;
        self::$exited++;
    }
}

#[ProcessAttr(command: "/bin/sh -c 'echo r'", restart: true)]
final class RestartFixture
{
    public static int $exits = 0;

    #[OnExit]
    public function onExit(int $code): void
    {
        self::$exits++;
    }
}
