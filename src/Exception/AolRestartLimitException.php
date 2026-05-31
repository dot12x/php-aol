<?php

declare(strict_types=1);

namespace Aol\Exception;

/**
 * Raised when a #[Restart]-enabled worker pool exceeds its rate
 * limit (max restarts within window). After this exception, the pool
 * is considered dead — no further restarts will be attempted.
 */
class AolRestartLimitException extends AolException
{
}
