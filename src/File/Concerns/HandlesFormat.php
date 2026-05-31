<?php

declare(strict_types=1);

namespace Aol\File\Concerns;

use function Amp\File\openFile;

/**
 * @internal Used only by Aol\File.
 */
trait HandlesFormat
{
    public static function readJson(string $path, bool $assoc = true): mixed
    {
        return \json_decode(self::read($path), $assoc, flags: \JSON_THROW_ON_ERROR);
    }

    public static function writeJson(string $path, mixed $value, int $flags = \JSON_PRETTY_PRINT, bool $atomic = false): void
    {
        self::write($path, \json_encode($value, $flags | \JSON_THROW_ON_ERROR), $atomic);
    }

    /**
     * Lazy line-by-line iterator.
     *
     * @return \Generator<int, string>
     */
    public static function readLines(string $path): \Generator
    {
        $h = openFile($path, 'r');
        try {
            $buffer = '';
            $i = 0;
            while (!$h->eof()) {
                $chunk = $h->read();
                if ($chunk === null) {
                    break;
                }
                $buffer .= $chunk;
                while (($pos = \strpos($buffer, "\n")) !== false) {
                    yield $i++ => \substr($buffer, 0, $pos);
                    $buffer = \substr($buffer, $pos + 1);
                }
            }
            if ($buffer !== '') {
                yield $i => $buffer;
            }
        } finally {
            $h->close();
        }
    }

    /**
     * Lazy chunked byte stream.
     *
     * @return \Generator<int, string>
     */
    public static function stream(string $path, int $chunkSize = 8192): \Generator
    {
        $h = openFile($path, 'r');
        try {
            $i = 0;
            while (!$h->eof()) {
                $chunk = $h->read(length: $chunkSize);
                if ($chunk === null) {
                    break;
                }
                yield $i++ => $chunk;
            }
        } finally {
            $h->close();
        }
    }
}
