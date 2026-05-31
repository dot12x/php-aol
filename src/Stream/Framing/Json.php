<?php

declare(strict_types=1);

namespace Aol\Stream\Framing;

use Aol\Stream\Connection;

/**
 * Newline-delimited JSON framing. Each frame is one JSON value
 * followed by a single \n.
 */
final class Json implements Framing
{
    private readonly Line $line;

    public function __construct()
    {
        $this->line = new Line("\n");
    }

    public function readFrame(Connection $conn): ?string
    {
        return $this->line->readFrame($conn);
    }

    public function writeFrame(Connection $conn, string $payload): void
    {
        $this->line->writeFrame($conn, $payload);
    }

    public function readDecoded(Connection $conn): mixed
    {
        $raw = $this->readFrame($conn);
        return $raw === null ? null : \json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
    }

    public function writeEncoded(Connection $conn, mixed $value): void
    {
        $this->writeFrame($conn, \json_encode($value, \JSON_THROW_ON_ERROR));
    }
}
