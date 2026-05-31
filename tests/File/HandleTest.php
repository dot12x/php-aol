<?php

declare(strict_types=1);

namespace Aol\Tests\File;

use Aol\Aol;
use Aol\File;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HandleTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/aol-handle-test-' . \bin2hex(\random_bytes(4));
        Aol::scope(fn () => File::mkdir($this->tmpDir));
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && File::exists($this->tmpDir)) {
            Aol::scope(fn () => File::rmdir($this->tmpDir, recursive: true));
        }
    }

    #[Test]
    public function readAllReturnsFullContents(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/full.txt";
            File::write($path, 'hello world');
            $h = File::open($path, 'r');
            try {
                self::assertSame('hello world', $h->readAll());
            } finally {
                $h->close();
            }
        });
    }

    #[Test]
    public function seekAndReadFromOffset(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/seek.txt";
            File::write($path, 'abcdefghij');
            $h = File::open($path, 'r');
            try {
                $h->seek(3);
                self::assertSame(3, $h->tell());
                $chunk = $h->read(4);
                self::assertSame('defg', $chunk);
            } finally {
                $h->close();
            }
        });
    }

    #[Test]
    public function writeAndTruncate(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/w.bin";
            $h = File::open($path, 'w+');
            try {
                $h->write('1234567890');
                $h->seek(0);
                self::assertSame('1234567890', $h->readAll());

                $h->truncate(5);
                $h->seek(0);
                self::assertSame('12345', $h->readAll());
            } finally {
                $h->close();
            }
        });
    }

    #[Test]
    public function closeIsIdempotent(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/c.txt";
            File::write($path, 'x');
            $h = File::open($path, 'r');
            $h->close();
            self::assertTrue($h->isClosed());
            $h->close(); // second close: no-op
            self::assertTrue($h->isClosed());
        });
    }
}
