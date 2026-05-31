<?php

declare(strict_types=1);

namespace Aol\Tests\Stream;

use Aol\Aol;
use Aol\Stream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests: TCP listen + connect roundtrip within a single Aol::scope.
 *
 * A server fiber accepts one connection and echoes the payload back;
 * a client fiber writes, reads the echo, then closes.
 * Both sides run inside one Aol::scope so Revolt drives all I/O.
 */
final class ConnectListenTest extends TestCase
{
    #[Test]
    public function testConnectListenRoundtrip(): void
    {
        $reply = Aol::scope(function (): string {
            $listener = Stream::listen('tcp://127.0.0.1:0');
            $addr     = 'tcp://' . $listener->address();

            Aol::async(static function () use ($listener): void {
                foreach ($listener->accept() as $client) {
                    $data = $client->read(64);
                    $client->write('echo:' . (string) $data);
                    $client->close();
                    $listener->close();
                    return;
                }
            });

            $conn     = Stream::connect($addr);
            $conn->write('ping');
            $response = (string) $conn->read(64);
            $conn->close();

            return $response;
        });

        self::assertStringStartsWith('echo:', $reply);
        self::assertStringContainsString('ping', $reply);
    }

    #[Test]
    public function testConnectToRefusedPortThrows(): void
    {
        $port = $this->findFreePort();

        $this->expectException(\Throwable::class);

        Aol::scope(static function () use ($port): void {
            Stream::connect('tcp://127.0.0.1:' . $port);
        });
    }

    /**
     * Binds momentarily to find a free TCP port, then releases it.
     * The port is very likely still free by the time the test uses it.
     */
    private function findFreePort(): int
    {
        $server = \stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($server === false) {
            self::markTestSkipped('Cannot probe for free port: ' . $errstr);
        }

        $name = \stream_socket_get_name($server, false);
        \fclose($server);

        if ($name === false) {
            self::markTestSkipped('Cannot read bound address for free-port probe.');
        }

        $colon = \strrpos($name, ':');

        return (int) \substr($name, $colon + 1);
    }
}
