<?php

declare(strict_types=1);

namespace Aol\Stream\Framing;

use Aol\Stream\Connection;
use Aol\Support\Cast;

/**
 * Line-delimited framing (Redis/SMTP-style). Reads/writes are
 * separated by the given delimiter.
 */
final class Line implements Framing
{
    /** @var array<int, string> Buffer per connection (spl_object_id keyed) */
    private static array $buffers = [];

    public function __construct(private readonly string $delimiter = "\r\n")
    {
    }

    public function readFrame(Connection $conn): ?string
    {
        $id = \spl_object_id($conn);
        $buffer = Cast::pick(self::$buffers, $id)->defaultValue('')->toString();

        while (($pos = \strpos($buffer, $this->delimiter)) === false) {
            $chunk = $conn->read();
            if ($chunk === null) {
                if ($buffer === '') {
                    return null;
                }
                self::$buffers[$id] = '';
                return $buffer;
            }
            $buffer .= $chunk;
        }

        $frame = \substr($buffer, 0, $pos);
        self::$buffers[$id] = \substr($buffer, $pos + \strlen($this->delimiter));
        return $frame;
    }

    public function writeFrame(Connection $conn, string $payload): void
    {
        $conn->write($payload . $this->delimiter);
    }
}
