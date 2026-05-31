<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Http;
use Aol\Time;

echo "== imperative Http::sse() demo ==\n";
echo "Hits sse.dev/test (public SSE endpoint emitting JSON ticks).\n";
echo "Ctrl-C to stop, or we'll auto-close after ~3 seconds.\n\n";

Aol::scope(function () {
    Aol::asyncBackground(function () {
        Time::sleep(3.0);
        throw new \RuntimeException('demo stop');
    });

    try {
        foreach (Http::sse('https://sse.dev/test') as $event) {
            $preview = \strlen($event->data) > 80
                ? \substr($event->data, 0, 80) . '...'
                : $event->data;
            echo "[{$event->event}] {$preview}\n";
        }
    } catch (\Throwable $e) {
        if ($e->getMessage() !== 'demo stop') {
            throw $e;
        }
    }
});

echo "\ndone.\n";
