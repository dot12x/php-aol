<?php

declare(strict_types=1);

namespace Aol\Exception;

/**
 * @internal Thrown into Pendings cancelled because a sibling crashed or a timeout fired.
 */
class AolCancelledException extends AolException
{
}
