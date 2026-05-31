<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Attribute\OnAwake;
use Aol\Attribute\OnSignal;
use Aol\Attribute\OnTick;
use Aol\Attribute\Worker;
use Aol\Time;

#[Worker]
final class TinyDaemon
{
    public int $ticks = 0;

    #[OnAwake]
    public function start(): void
    {
        echo "  [awake] daemon started\n";
    }

    #[OnTick(every: 0.2)]
    public function heartbeat(): void
    {
        $this->ticks++;
        echo "  [tick #{$this->ticks}]\n";
    }

    #[OnSignal(SIGUSR2)]
    public function poked(): void
    {
        echo "  [signal] SIGUSR2 received\n";
    }
}

echo "== daemon demo (1.1s window, OnTick + OnSignal) ==\n\n";

$t0 = \microtime(true);

Aol::scope(function () {
    $d = Aol::wrap(TinyDaemon::class);

    Aol::asyncBackground(static function (): void {
        Time::sleep(0.45);
        \posix_kill(\getmypid() ?: 0, SIGUSR2);
    });

    Time::sleep(1.1);
    $_ = $d;
});

$elapsed = \round((\microtime(true) - $t0) * 1000);
echo "\n== daemon stopped cleanly after {$elapsed}ms ==\n";
