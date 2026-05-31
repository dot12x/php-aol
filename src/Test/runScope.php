<?php

declare(strict_types=1);

namespace Aol\Test;

use Aol\Internal\Scope;
use Aol\Internal\ScopeStack;
use Aol\Time\Clock;
use Amp\Future;
use function Amp\async;

/**
 * Run a scope on a custom clock (typically FakeClock) and return a
 * Future the test can manipulate. The body runs inside a spawned
 * fiber so the test fiber stays free to call $clock->advance()
 * between checkpoints.
 *
 * @return Future<mixed>
 */
function runScope(Clock $clock, callable $body, ?float $timeout = null): Future
{
    return async(static function () use ($clock, $body, $timeout): mixed {
        $scope = new Scope(
            parent: ScopeStack::current(),
            clock: $clock,
            timeout: $timeout,
        );
        ScopeStack::push($scope);
        try {
            return $scope->collect($body);
        } finally {
            ScopeStack::pop($scope);
        }
    });
}
