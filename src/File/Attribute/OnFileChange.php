<?php

declare(strict_types=1);

namespace Aol\File\Attribute;

/**
 * Method-level: invoked whenever the named path changes. The method
 * receives a FileEvent. Honored by Wrapper since Phase 1 (2026-05-29);
 * the watch runs as an asyncBackground task tied to the surrounding
 * scope.
 *
 * The `recursive` flag is accepted but currently a no-op — File::watch
 * does not yet support recursive walking. A directory path is watched
 * as a flat listing of its immediate file children.
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD)]
final readonly class OnFileChange
{
    public function __construct(
        public string $path,
        public bool $recursive = false,
        public float $pollInterval = 1.0,
    ) {
    }
}
