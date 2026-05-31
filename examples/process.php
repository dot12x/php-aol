<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Process\Attribute\OnExit;
use Aol\Process\Attribute\OnStdout;
use Aol\Process\Attribute\Process as ProcessAttr;
use Aol\Time;

#[ProcessAttr(command: "/bin/sh -c 'i=0; while [ \$i -lt 3 ]; do echo tick-\$i; i=\$((i+1)); sleep 0.15; done'")]
final class TinyChild
{
    #[OnStdout]
    public function log(string $line): void
    {
        echo "  [stdout] {$line}\n";
    }

    #[OnExit]
    public function done(int $code): void
    {
        echo "  [exit ] code={$code}\n";
    }
}

echo "== declarative process demo ==\n\n";

Aol::scope(function () {
    $c = Aol::wrap(TinyChild::class);
    Time::sleep(0.8);
    $_ = $c;
});

echo "\n== done ==\n";
