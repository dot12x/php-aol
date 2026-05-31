<?php

declare(strict_types=1);

namespace Aol\Tests\Queue;

use Aol\Aol;
use Aol\Queue\Queue;
use Aol\Queue\Topic;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueTest extends TestCase
{
    #[Test]
    public function pushAndPopFifo(): void
    {
        $items = Aol::scope(function () {
            $q = new Queue();
            $q->push('a');
            $q->push('b');
            $q->push('c');
            $q->close();

            $out = [];
            while (($item = $q->pop()) !== null) {
                $out[] = $item;
            }
            return $out;
        });
        self::assertSame(['a', 'b', 'c'], $items);
    }

    #[Test]
    public function foreachIteratesUntilClosed(): void
    {
        $items = Aol::scope(function () {
            $q = new Queue();
            $q->push(1);
            $q->push(2);
            $q->push(3);
            $q->close();

            $out = [];
            foreach ($q as $item) {
                $out[] = $item;
            }
            return $out;
        });
        self::assertSame([1, 2, 3], $items);
    }

    #[Test]
    public function popReturnsNullAfterCloseAndDrain(): void
    {
        Aol::scope(function () {
            $q = new Queue();
            $q->push('only');
            $q->close();
            self::assertSame('only', $q->pop());
            self::assertNull($q->pop());
            return null;
        });
    }

    #[Test]
    public function negativeCapacityRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Queue(-1);
    }

    #[Test]
    public function topicFansOutToAllSubscribers(): void
    {
        $result = Aol::scope(function () {
            $topic = new Topic();
            $sub1 = $topic->subscribe();
            $sub2 = $topic->subscribe();

            $topic->publish('hello');
            $topic->publish('world');
            $topic->close();

            $items1 = [];
            $items2 = [];
            foreach ($sub1 as $item) {
                $items1[] = $item;
            }
            foreach ($sub2 as $item) {
                $items2[] = $item;
            }
            return [$items1, $items2];
        });

        self::assertSame(['hello', 'world'], $result[0]);
        self::assertSame(['hello', 'world'], $result[1]);
    }

    #[Test]
    public function lateSubscriberSeesOnlyNewItems(): void
    {
        $result = Aol::scope(function () {
            $topic = new Topic();
            $early = $topic->subscribe();

            $topic->publish('first');

            $late = $topic->subscribe();
            $topic->publish('second');
            $topic->close();

            return [
                'early' => \iterator_to_array($early, false),
                'late' => \iterator_to_array($late, false),
            ];
        });

        self::assertSame(['first', 'second'], $result['early']);
        self::assertSame(['second'], $result['late']);
    }

    #[Test]
    public function subscribeAfterCloseRejected(): void
    {
        $topic = new Topic();
        $topic->close();
        $this->expectException(\LogicException::class);
        $topic->subscribe();
    }
}
