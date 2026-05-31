<?php

declare(strict_types=1);

namespace Tests\Process;

use Aol\Aol;
use Aol\Process;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\is_executable('/bin/sh')) {
            self::markTestSkipped('/bin/sh required');
        }
    }

    public function testRunCapturesStdoutAndExitCode(): void
    {
        $result = Aol::scope(fn () => Process::run(['/bin/sh', '-c', 'echo hello; exit 0']));

        self::assertSame(0, $result->exitCode);
        self::assertTrue($result->ok());
        self::assertSame("hello\n", $result->stdout);
    }

    public function testRunCapturesStderrAndNonZeroExit(): void
    {
        $result = Aol::scope(fn () => Process::run(['/bin/sh', '-c', 'echo err 1>&2; exit 3']));

        self::assertSame(3, $result->exitCode);
        self::assertFalse($result->ok());
        self::assertSame("err\n", $result->stderr);
    }

    public function testRunPipesStdin(): void
    {
        $result = Aol::scope(fn () => Process::run(['/bin/cat'], stdin: "piped\n"));

        self::assertSame(0, $result->exitCode);
        self::assertSame("piped\n", $result->stdout);
    }
}
