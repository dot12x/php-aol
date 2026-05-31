<?php

declare(strict_types=1);

namespace Tests\Wrap;

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\File\Attribute\OnFileChange;
use Aol\File\FileEvent;
use Aol\Time;
use PHPUnit\Framework\TestCase;

final class OnFileChangeTest extends TestCase
{
    public static int $events = 0;

    protected function setUp(): void
    {
        self::$events = 0;
        @\unlink(WatchedFixture::PATH);
        \file_put_contents(WatchedFixture::PATH, "v1\n");
    }

    protected function tearDown(): void
    {
        @\unlink(WatchedFixture::PATH);
    }

    public function testOnFileChangeFiresWhenFileChanges(): void
    {
        Aol::scope(function () {
            $w = Aol::wrap(WatchedFixture::class);

            Aol::async(static function (): void {
                Time::sleep(0.12);
                \file_put_contents(WatchedFixture::PATH, "v2\n");
                Time::sleep(0.12);
                \file_put_contents(WatchedFixture::PATH, "v3\n");
            });

            Time::sleep(0.5);
            $_ = $w;
        });

        self::assertGreaterThanOrEqual(1, self::$events, 'at least one change event expected');
    }
}

final class WatchedFixture
{
    public const PATH = '/tmp/aol_onfilechange_fixture.txt';

    #[OnFileChange(path: self::PATH, pollInterval: 0.05)]
    public function onChange(FileEvent $e): void
    {
        OnFileChangeTest::$events++;
    }
}
