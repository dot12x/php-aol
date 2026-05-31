<?php

declare(strict_types=1);

namespace Aol\Exception;

/**
 * Thrown when Aol::wrap() cannot instantiate or fully awaken the
 * pool — e.g. a constructor throws, a container resolution fails,
 * or an #[OnAwake] hook raises.
 */
class AolWrapException extends AolException
{
}
