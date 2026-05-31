<?php

declare(strict_types=1);

namespace Aol\Tests\File;

use Aol\Aol;
use Aol\File;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileBasicTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/aol-file-test-' . \bin2hex(\random_bytes(4));
        Aol::scope(fn () => File::mkdir($this->tmpDir));
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && File::exists($this->tmpDir)) {
            Aol::scope(fn () => File::rmdir($this->tmpDir, recursive: true));
        }
    }

    #[Test]
    public function writeAndReadRoundTrip(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/hello.txt";
            File::write($path, 'Salom dunyo');
            self::assertSame('Salom dunyo', File::read($path));
        });
    }

    #[Test]
    public function existsReturnsCorrectBool(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/maybe.txt";
            self::assertFalse(File::exists($path));
            File::write($path, '');
            self::assertTrue(File::exists($path));
        });
    }

    #[Test]
    public function statReturnsMetadata(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/data.txt";
            File::write($path, 'twelve chars');
            $s = File::stat($path);
            self::assertNotNull($s);
            self::assertSame(12, $s->size);
            self::assertTrue($s->isFile);
            self::assertFalse($s->isDir);
        });
    }

    #[Test]
    public function deleteRemovesFile(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/gone.txt";
            File::write($path, 'x');
            File::delete($path);
            self::assertFalse(File::exists($path));
        });
    }

    #[Test]
    public function copyAndMove(): void
    {
        Aol::scope(function () {
            $a = "{$this->tmpDir}/a.txt";
            $b = "{$this->tmpDir}/b.txt";
            $c = "{$this->tmpDir}/c.txt";
            File::write($a, 'src');
            File::copy($a, $b);
            self::assertSame('src', File::read($b));
            File::move($b, $c);
            self::assertFalse(File::exists($b));
            self::assertSame('src', File::read($c));
        });
    }

    #[Test]
    public function appendExtendsExistingFile(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/log.txt";
            File::write($path, "one\n");
            File::append($path, "two\n");
            File::append($path, "three\n");
            self::assertSame("one\ntwo\nthree\n", File::read($path));
        });
    }

    #[Test]
    public function atomicWriteReplacesContents(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/config.json";
            File::write($path, 'old', atomic: true);
            File::write($path, 'new', atomic: true);
            self::assertSame('new', File::read($path));
        });
    }

    #[Test]
    public function mkdirRecursiveAndRmdirRecursive(): void
    {
        Aol::scope(function () {
            $deep = "{$this->tmpDir}/a/b/c";
            File::mkdir($deep);
            self::assertTrue(File::exists($deep));
            File::write("{$deep}/file.txt", 'x');
            File::rmdir("{$this->tmpDir}/a", recursive: true);
            self::assertFalse(File::exists("{$this->tmpDir}/a"));
        });
    }

    #[Test]
    public function jsonRoundTrip(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/data.json";
            File::writeJson($path, ['name' => 'aol', 'version' => 1, 'tags' => ['async', 'php']]);
            $decoded = File::readJson($path);
            self::assertSame(['name' => 'aol', 'version' => 1, 'tags' => ['async', 'php']], $decoded);
        });
    }

    #[Test]
    public function readLinesIteratesLineByLine(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/lines.txt";
            File::write($path, "alpha\nbeta\ngamma\n");
            $lines = \iterator_to_array(File::readLines($path), false);
            self::assertSame(['alpha', 'beta', 'gamma'], $lines);
        });
    }

    #[Test]
    public function streamReadsInChunks(): void
    {
        Aol::scope(function () {
            $path = "{$this->tmpDir}/chunks.bin";
            $data = \str_repeat('x', 25_000);
            File::write($path, $data);
            $chunks = \iterator_to_array(File::stream($path, chunkSize: 8192), false);
            self::assertSame($data, \implode('', $chunks));
            self::assertGreaterThan(1, \count($chunks));
        });
    }
}
