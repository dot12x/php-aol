<?php

declare(strict_types=1);

namespace Aol\Tests\File;

use Aol\Aol;
use Aol\File;
use Aol\File\DirEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DirectoryTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/aol-dir-test-' . \bin2hex(\random_bytes(4));
        Aol::scope(function () {
            File::mkdir($this->tmpDir);
            File::write("{$this->tmpDir}/a.txt", '');
            File::write("{$this->tmpDir}/b.txt", '');
            File::mkdir("{$this->tmpDir}/sub");
            File::write("{$this->tmpDir}/sub/c.txt", '');
            File::mkdir("{$this->tmpDir}/sub/deep");
            File::write("{$this->tmpDir}/sub/deep/d.txt", '');
        });
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && File::exists($this->tmpDir)) {
            Aol::scope(fn () => File::rmdir($this->tmpDir, recursive: true));
        }
    }

    #[Test]
    public function listReturnsTopLevelOnly(): void
    {
        $names = Aol::scope(function () {
            $out = [];
            foreach (File::list($this->tmpDir) as $entry) {
                $out[] = $entry->name;
            }
            \sort($out);
            return $out;
        });
        self::assertSame(['a.txt', 'b.txt', 'sub'], $names);
    }

    #[Test]
    public function walkVisitsDescendants(): void
    {
        $names = Aol::scope(function () {
            $out = [];
            foreach (File::walk($this->tmpDir) as $entry) {
                $out[] = $entry->name;
            }
            \sort($out);
            return $out;
        });
        self::assertSame(['a.txt', 'b.txt', 'c.txt', 'd.txt', 'deep', 'sub'], $names);
    }

    #[Test]
    public function walkRespectsMaxDepth(): void
    {
        $names = Aol::scope(function () {
            $out = [];
            foreach (File::walk($this->tmpDir, maxDepth: 1) as $entry) {
                $out[] = $entry->name;
            }
            \sort($out);
            return $out;
        });
        // Only top-level (depth 0); subdirectories listed but not descended.
        self::assertSame(['a.txt', 'b.txt', 'sub'], $names);
    }

    #[Test]
    public function walkFilterSkipsEntries(): void
    {
        $names = Aol::scope(function () {
            $out = [];
            foreach (File::walk($this->tmpDir, filter: fn (DirEntry $e) => \str_ends_with($e->name, '.txt')) as $entry) {
                $out[] = $entry->name;
            }
            \sort($out);
            return $out;
        });
        self::assertSame(['a.txt', 'b.txt'], $names);
    }

    #[Test]
    public function dirEntryStatLazyButCorrect(): void
    {
        Aol::scope(function () {
            $entries = \iterator_to_array(File::list($this->tmpDir), false);
            $byName = [];
            foreach ($entries as $e) {
                $byName[$e->name] = $e;
            }
            self::assertTrue($byName['a.txt']->isFile());
            self::assertFalse($byName['a.txt']->isDir());
            self::assertTrue($byName['sub']->isDir());
            self::assertFalse($byName['sub']->isFile());
        });
    }
}
