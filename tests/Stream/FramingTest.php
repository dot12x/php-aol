<?php

declare(strict_types=1);

namespace Aol\Tests\Stream;

use Aol\Stream\Connection;
use Aol\Stream\Framing\Json;
use Aol\Stream\Framing\Length;
use Aol\Stream\Framing\Line;
use Amp\Socket\Socket;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the three built-in Stream framings: Line, Length, Json.
 *
 * Strategy: mock Amp\Socket\Socket so we can inject controlled read-chunks
 * and capture written bytes — then wrap it in Connection and drive the
 * framings directly.
 *
 * Static buffer isolation: each test builds a fresh Connection object,
 * giving it a unique spl_object_id, so tests never share buffer state.
 */
final class FramingTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build a Socket stub whose read() calls return the given chunks in order,
     * then null (EOF). Each consecutive call returns the next chunk.
     *
     * @param list<string> $chunks
     */
    private function socketReturning(array $chunks): Socket
    {
        $socket = $this->createStub(Socket::class);

        $idx = 0;
        $socket
            ->method('read')
            ->willReturnCallback(function () use ($chunks, &$idx): ?string {
                $val = $chunks[$idx] ?? null;
                $idx++;
                return $val;
            });

        return $socket;
    }

    /**
     * Build a Socket stub that captures writes and exposes them via a reference.
     */
    private function socketCapturingWrites(string &$captured): Socket
    {
        $socket = $this->createStub(Socket::class);

        $socket
            ->method('write')
            ->willReturnCallback(function (string $data) use (&$captured): void {
                $captured .= $data;
            });

        return $socket;
    }

    private function connectionFrom(Socket $socket): Connection
    {
        return new Connection($socket);
    }

    // -----------------------------------------------------------------------
    // LINE — encode
    // -----------------------------------------------------------------------

    #[Test]
    public function testLineEncode(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Line("\r\n");

        $framing->writeFrame($conn, 'hello');

        self::assertSame("hello\r\n", $written);
    }

    #[Test]
    public function testLineEncodeCustomDelimiter(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Line("\n");

        $framing->writeFrame($conn, 'world');

        self::assertSame("world\n", $written);
    }

    // -----------------------------------------------------------------------
    // LINE — decode: one frame in one chunk
    // -----------------------------------------------------------------------

    #[Test]
    public function testLineDecodeOneFrame(): void
    {
        $conn = $this->connectionFrom($this->socketReturning(["hello\r\n"]));
        $framing = new Line("\r\n");

        $frame = $framing->readFrame($conn);

        self::assertSame('hello', $frame);
    }

    // -----------------------------------------------------------------------
    // LINE — decode: frame split across two chunks
    // -----------------------------------------------------------------------

    #[Test]
    public function testLineDecodeSplitFrame(): void
    {
        // "hel" arrives first, then "lo\r\n" — delimiter only present in 2nd chunk
        $conn = $this->connectionFrom($this->socketReturning(['hel', "lo\r\n"]));
        $framing = new Line("\r\n");

        $frame = $framing->readFrame($conn);

        self::assertSame('hello', $frame);
    }

    // -----------------------------------------------------------------------
    // LINE — decode: two complete frames in a single chunk
    // -----------------------------------------------------------------------

    #[Test]
    public function testLineDecodeMultipleFramesInOneChunk(): void
    {
        // Both frames land in the same TCP chunk.
        $conn = $this->connectionFrom($this->socketReturning(["first\r\nsecond\r\n"]));
        $framing = new Line("\r\n");

        $frame1 = $framing->readFrame($conn);
        // The leftover "second\r\n" is in the static buffer keyed by spl_object_id($conn).
        // Second call must drain that buffer (no extra socket read needed).
        $frame2 = $framing->readFrame($conn);

        self::assertSame('first', $frame1);
        self::assertSame('second', $frame2);
    }

    // -----------------------------------------------------------------------
    // LINE — state isolation between two connections
    // -----------------------------------------------------------------------

    #[Test]
    public function testLineIsolationBetweenConnections(): void
    {
        $framing = new Line("\n");

        // connA will receive its frame in two pieces: "A-" then "msg\n"
        $connA = $this->connectionFrom($this->socketReturning(['A-', "msg\n"]));
        // connB has its own complete frame ready
        $connB = $this->connectionFrom($this->socketReturning(["B-msg\n"]));

        // Read from B first (should not disturb A's buffer)
        $frameB = $framing->readFrame($connB);
        $frameA = $framing->readFrame($connA);

        self::assertSame('B-msg', $frameB);
        self::assertSame('A-msg', $frameA);
    }

    // -----------------------------------------------------------------------
    // LENGTH — encode (default 4-byte big-endian prefix)
    // -----------------------------------------------------------------------

    #[Test]
    public function testLengthEncode(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Length(4);

        $framing->writeFrame($conn, 'hello');

        $expectedPrefix = \pack('N', 5); // 5 bytes, big-endian uint32
        self::assertSame($expectedPrefix . 'hello', $written);
    }

    #[Test]
    public function testLengthEncode1Byte(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Length(1);

        $framing->writeFrame($conn, 'hi');

        self::assertSame(\chr(2) . 'hi', $written);
    }

    #[Test]
    public function testLengthEncode2Bytes(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Length(2);

        $framing->writeFrame($conn, 'ab');

        self::assertSame(\pack('n', 2) . 'ab', $written);
    }

    // -----------------------------------------------------------------------
    // LENGTH — decode: one frame in one chunk
    // -----------------------------------------------------------------------

    #[Test]
    public function testLengthDecodeOneFrame(): void
    {
        $payload = 'hello';
        $wire = \pack('N', \strlen($payload)) . $payload;

        $conn = $this->connectionFrom($this->socketReturning([$wire]));
        $framing = new Length(4);

        $frame = $framing->readFrame($conn);

        self::assertSame('hello', $frame);
    }

    // -----------------------------------------------------------------------
    // LENGTH — decode: header and body arrive in separate chunks
    // -----------------------------------------------------------------------

    #[Test]
    public function testLengthDecodeSplitFrame(): void
    {
        $payload = 'hello';
        $wire = \pack('N', \strlen($payload)) . $payload;

        // Split: first 2 bytes of the 4-byte header, then the rest
        $chunk1 = \substr($wire, 0, 2);
        $chunk2 = \substr($wire, 2);

        $conn = $this->connectionFrom($this->socketReturning([$chunk1, $chunk2]));
        $framing = new Length(4);

        $frame = $framing->readFrame($conn);

        self::assertSame('hello', $frame);
    }

    #[Test]
    public function testLengthDecodeSplitPayload(): void
    {
        $payload = 'world';
        $wire = \pack('N', \strlen($payload)) . $payload;

        // Header arrives complete; payload split across two chunks
        $header = \substr($wire, 0, 4);
        $body1  = \substr($wire, 4, 2); // "wo"
        $body2  = \substr($wire, 6);    // "rld"

        $conn = $this->connectionFrom($this->socketReturning([$header, $body1, $body2]));
        $framing = new Length(4);

        $frame = $framing->readFrame($conn);

        self::assertSame('world', $frame);
    }

    // -----------------------------------------------------------------------
    // LENGTH — constructor rejects invalid byte widths
    // -----------------------------------------------------------------------

    #[Test]
    public function testLengthRejectsInvalidByteWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Length(3);
    }

    // -----------------------------------------------------------------------
    // JSON — encode
    // -----------------------------------------------------------------------

    #[Test]
    public function testJsonEncode(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Json();

        $framing->writeFrame($conn, \json_encode(['a' => 1], \JSON_THROW_ON_ERROR));

        self::assertSame('{"a":1}' . "\n", $written);
    }

    #[Test]
    public function testJsonWriteEncoded(): void
    {
        $written = '';
        $conn = $this->connectionFrom($this->socketCapturingWrites($written));
        $framing = new Json();

        $framing->writeEncoded($conn, ['key' => 'value', 'n' => 42]);

        self::assertSame('{"key":"value","n":42}' . "\n", $written);
    }

    // -----------------------------------------------------------------------
    // JSON — decode: one frame in one chunk
    // -----------------------------------------------------------------------

    #[Test]
    public function testJsonDecodeOneFrame(): void
    {
        $raw = '{"x":99}' . "\n";
        $conn = $this->connectionFrom($this->socketReturning([$raw]));
        $framing = new Json();

        $frame = $framing->readFrame($conn);

        self::assertSame('{"x":99}', $frame);
    }

    #[Test]
    public function testJsonReadDecoded(): void
    {
        $raw = '{"a":1,"b":2}' . "\n";
        $conn = $this->connectionFrom($this->socketReturning([$raw]));
        $framing = new Json();

        $decoded = $framing->readDecoded($conn);

        self::assertSame(['a' => 1, 'b' => 2], $decoded);
    }

    // -----------------------------------------------------------------------
    // JSON — decode: frame split across two chunks
    // -----------------------------------------------------------------------

    #[Test]
    public function testJsonDecodeSplitFrame(): void
    {
        // JSON arrives in two pieces before the trailing \n
        $conn = $this->connectionFrom($this->socketReturning(['{"z":', "99}\n"]));
        $framing = new Json();

        $frame = $framing->readFrame($conn);

        self::assertSame('{"z":99}', $frame);
    }

    // -----------------------------------------------------------------------
    // JSON — decode: two complete frames in one chunk
    // -----------------------------------------------------------------------

    #[Test]
    public function testJsonDecodeMultipleFramesInOneChunk(): void
    {
        $raw = '{"i":1}' . "\n" . '{"i":2}' . "\n";
        $conn = $this->connectionFrom($this->socketReturning([$raw]));
        $framing = new Json();

        $frame1 = $framing->readDecoded($conn);
        $frame2 = $framing->readDecoded($conn);

        self::assertSame(['i' => 1], $frame1);
        self::assertSame(['i' => 2], $frame2);
    }
}
