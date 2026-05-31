<?php

declare(strict_types=1);

namespace Aol\File;

/**
 * Filesystem change notification, yielded by File::watch() and
 * delivered to #[OnFileChange] methods.
 */
final readonly class FileEvent
{
    public function __construct(
        public string $path,
        public FileEventType $type,
        public float $at,
    ) {
    }
}
