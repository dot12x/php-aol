<?php

declare(strict_types=1);

namespace Aol\Tests\File;

use Aol\Aol;
use Aol\File;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TempLockTest extends TestCase
{
    #[Test]
    public function tempFileAutoCleanupAtScopeClose(): void
    {
        $capturedPath = '';

        Aol::scope(function () use (&$capturedPath) {
            $h = File::temp();
            $capturedPath = $h->path();
            $h->write('data');
            $h->close();
            self::assertTrue(File::exists($capturedPath));
        });

        self::assertFalse(File::exists($capturedPath), 'Temp file must be removed when scope closes.');
    }

    #[Test]
    public function tempDirAutoCleanup(): void
    {
        $captured = '';

        Aol::scope(function () use (&$captured) {
            $captured = File::tempDir();
            File::write("{$captured}/inner.txt", 'x');
            self::assertTrue(File::exists("{$captured}/inner.txt"));
        });

        self::assertFalse(File::exists($captured));
    }

    #[Test]
    public function keepTempPreventsCleanup(): void
    {
        $captured = '';

        Aol::scope(function () use (&$captured) {
            $h = File::temp();
            $captured = $h->path();
            $h->write('keep me');
            $h->close();
            File::keepTemp($captured);
        });

        self::assertTrue(File::exists($captured));
        Aol::scope(fn () => File::delete($captured));
    }

    #[Test]
    public function tempOutsideScopeStaysAround(): void
    {
        // Outside any scope: no cleanup wiring.
        $h = File::temp();
        $path = $h->path();
        $h->close();

        self::assertTrue(File::exists($path));
        File::delete($path);
    }

    #[Test]
    public function withLockExclusiveSerializesRead(): void
    {
        // Note: amphp/file's ParallelFile driver has issues with
        // truncate-then-write inside a single handle. Use lock for
        // read, then write the new value via the static facade.
        Aol::scope(function () {
            $tmpDir = File::tempDir();
            $path = "{$tmpDir}/counter.txt";
            File::write($path, '0');

            $next = File::withLock($path, fn ($h) => (int) $h->readAll() + 1);
            File::write($path, (string) $next, atomic: true);

            self::assertSame(1, $next);
            self::assertSame('1', File::read($path));
        });
    }

    #[Test]
    public function withLockReleasesOnException(): void
    {
        Aol::scope(function () {
            $dir = File::tempDir();
            $path = "{$dir}/lock.txt";
            File::write($path, 'x');

            try {
                File::withLock($path, function ($h) {
                    throw new \RuntimeException('boom');
                });
            } catch (\RuntimeException $e) {
                self::assertSame('boom', $e->getMessage());
            }

            // Next acquisition must work (lock was released).
            $ok = File::withLock($path, fn ($h) => 'ok');
            self::assertSame('ok', $ok);
        });
    }
}
