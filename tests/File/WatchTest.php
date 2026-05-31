<?php

declare(strict_types=1);

namespace Aol\Tests\File;

use Aol\Aol;
use Aol\File;
use Aol\File\FileEvent;
use Aol\File\FileEventType;
use Aol\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WatchTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = \sys_get_temp_dir() . '/aol-watch-test-' . \bin2hex(\random_bytes(4));
        Aol::scope(fn () => File::mkdir($this->tmpDir));
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '' && File::exists($this->tmpDir)) {
            Aol::scope(fn () => File::rmdir($this->tmpDir, recursive: true));
        }
    }

    #[Test]
    public function watchYieldsCreatedEventForNewFile(): void
    {
        $events = [];

        Aol::scope(timeout: 1.5, body: function () use (&$events) {
            $dir = $this->tmpDir;
            Aol::async(function () use ($dir) {
                Time::sleep(0.1);
                File::write("{$dir}/new.txt", 'hi');
                Time::sleep(0.4);
            });

            try {
                foreach (File::watch($dir, interval: 0.1) as $event) {
                    $events[] = $event;
                    break;
                }
            } catch (\Throwable) {
                // scope timeout or cancellation will end the watch
            }
        });

        self::assertCount(1, $events);
        self::assertSame(FileEventType::Created, $events[0]->type);
        self::assertStringEndsWith('/new.txt', $events[0]->path);
    }

    #[Test]
    public function watchYieldsDeletedEvent(): void
    {
        Aol::scope(fn () => File::write("{$this->tmpDir}/doomed.txt", 'x'));

        $events = [];

        Aol::scope(timeout: 1.5, body: function () use (&$events) {
            $dir = $this->tmpDir;
            Aol::async(function () use ($dir) {
                Time::sleep(0.1);
                File::delete("{$dir}/doomed.txt");
                Time::sleep(0.4);
            });

            try {
                foreach (File::watch($dir, interval: 0.1) as $event) {
                    $events[] = $event;
                    break;
                }
            } catch (\Throwable) {
            }
        });

        self::assertCount(1, $events);
        self::assertSame(FileEventType::Deleted, $events[0]->type);
    }
}
