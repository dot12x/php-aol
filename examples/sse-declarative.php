<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Http\Attribute\OnSse;
use Aol\Http\Sse\SseEvent;
use Aol\Time;

#[Worker]
final class TickPrinter
{
    public int $count = 0;

    #[OnSse('https://sse.dev/test')]
    public function rx(SseEvent $event): void
    {
        $this->count++;
        $preview = \strlen($event->data) > 60
            ? \substr($event->data, 0, 60) . '...'
            : $event->data;
        echo "#{$this->count} [{$event->event}] {$preview}\n";
    }
}

echo "== declarative #[OnSse] demo ==\n";
echo "Wrapped class subscribes via attribute; handler runs per event.\n";
echo "Auto-closes after ~3 seconds.\n\n";

Aol::scope(function () {
    $w = Aol::wrap(TickPrinter::class);
    Time::sleep(3.0);
    $_ = $w;
});

echo "\ndone.\n";
