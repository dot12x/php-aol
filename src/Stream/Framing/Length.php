<?php

declare(strict_types=1);

namespace Aol\Stream\Framing;

use Aol\Stream\Connection;
use Aol\Support\Cast;

/**
 * Length-prefixed framing for binary protocols. Each frame is
 * preceded by an N-byte big-endian length header (default 4).
 */
final class Length implements Framing
{
    /** @var array<int, string> */
    private static array $buffers = [];

    public function __construct(private readonly int $bytes = 4)
    {
        if (!\in_array($bytes, [1, 2, 4, 8], true)) {
            throw new \InvalidArgumentException('Length prefix must be 1, 2, 4 or 8 bytes.');
        }
    }

    public function readFrame(Connection $conn): ?string
    {
        $id = \spl_object_id($conn);
        $buffer = Cast::pick(self::$buffers, $id)->defaultValue('')->toString();

        while (\strlen($buffer) < $this->bytes) {
            $chunk = $conn->read();
            if ($chunk === null) {
                return null;
            }
            $buffer .= $chunk;
        }

        $len = $this->decodeLength(\substr($buffer, 0, $this->bytes));
        $buffer = \substr($buffer, $this->bytes);

        while (\strlen($buffer) < $len) {
            $chunk = $conn->read();
            if ($chunk === null) {
                return null;
            }
            $buffer .= $chunk;
        }

        $frame = \substr($buffer, 0, $len);
        self::$buffers[$id] = \substr($buffer, $len);
        return $frame;
    }

    public function writeFrame(Connection $conn, string $payload): void
    {
        $conn->write($this->encodeLength(\strlen($payload)) . $payload);
    }

    private function encodeLength(int $len): string
    {
        return match ($this->bytes) {
            1 => \chr($len & 0xff),
            2 => \pack('n', $len),
            4 => \pack('N', $len),
            8 => \pack('J', $len),
            default => throw new \LogicException('unreachable'),
        };
    }

    private function decodeLength(string $bytes): int
    {
        return match ($this->bytes) {
            1 => \ord($bytes),
            2 => $this->unpackInt('n', $bytes),
            4 => $this->unpackInt('N', $bytes),
            8 => $this->unpackInt('J', $bytes),
            default => throw new \LogicException('unreachable'),
        };
    }

    private function unpackInt(string $format, string $bytes): int
    {
        $u = \unpack($format, $bytes);
        return \is_array($u) ? Cast::pick($u, 1)->defaultValue(0)->toInt() : 0;
    }
}
