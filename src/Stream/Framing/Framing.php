<?php

declare(strict_types=1);

namespace Aol\Stream\Framing;

use Aol\Stream\Connection;

interface Framing
{
    public function readFrame(Connection $conn): ?string;

    public function writeFrame(Connection $conn, string $payload): void;
}
