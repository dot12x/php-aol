<?php

declare(strict_types=1);

namespace Aol\Attribute;

/**
 * Lifecycle hook: called once per instance when the owning scope
 * closes. All instances sleep in parallel; exceptions are swallowed
 * (best-effort cleanup).
 *
 * Only runs when the wrap was created inside an Aol::scope() — wraps
 * created outside any scope never see OnSleep (the application owns
 * their lifecycle).
 *
 * Method signature: () -> void.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class OnSleep
{
}
