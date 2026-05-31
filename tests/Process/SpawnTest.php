<?php

declare(strict_types=1);

namespace Tests\Process;

use Aol\Aol;
use Aol\Process;
use PHPUnit\Framework\TestCase;

final class SpawnTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\is_executable('/bin/sh')) {
            self::markTestSkipped('/bin/sh required');
        }
    }

    public function testSpawnStreamsStdoutLines(): void
    {
        $lines = Aol::scope(function () {
            $p = Process::spawn(['/bin/sh', '-c', 'echo a; echo b; echo c']);
            $out = [];
            foreach ($p->stdout() as $line) {
                $out[] = $line;
            }
            $p->wait();
            return $out;
        });

        self::assertSame(['a', 'b', 'c'], $lines);
    }

    public function testSpawnCanBeKilled(): void
    {
        $exit = Aol::scope(function () {
            $p = Process::spawn(['/bin/sh', '-c', 'sleep 5']);
            $p->kill(\SIGTERM);
            return $p->wait();
        });

        self::assertNotSame(0, $exit);
    }
}
