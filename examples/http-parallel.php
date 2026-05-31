<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Http;

echo "== parallel HTTP demo ==\n";
echo "Fetching 5 posts in parallel (jsonplaceholder.typicode.com).\n";
echo "Auto-graph: Aol::scope() dispatches every request on the same tick.\n\n";

function timeIt(callable $body): array
{
    $t0 = \microtime(true);
    $r = $body();
    $ms = \round((\microtime(true) - $t0) * 1000);
    return [$ms, $r];
}

[$seqMs, $seq] = timeIt(static function (): array {
    $out = [];
    for ($i = 1; $i <= 5; $i++) {
        $out[] = Aol::scope(static fn () => Http::get("https://jsonplaceholder.typicode.com/posts/{$i}"));
    }
    return $out;
});

[$parMs, $par] = timeIt(static function (): array {
    return Aol::scope(static function (): array {
        $pendings = [];
        for ($i = 1; $i <= 5; $i++) {
            $url = "https://jsonplaceholder.typicode.com/posts/{$i}";
            $pendings[] = Aol::async(static fn () => Http::get($url));
        }
        return $pendings;
    });
});

echo "sequential: {$seqMs}ms\n";
echo "parallel:   {$parMs}ms\n";
echo "speedup:    " . \round($seqMs / \max($parMs, 1), 1) . "x\n\n";

echo "posts fetched (from the parallel run):\n";
foreach ($par as $i => $resp) {
    $data = $resp->json();
    \assert(\is_array($data));
    $title = \substr((string) ($data['title'] ?? ''), 0, 50);
    echo "  [" . ($i + 1) . "] {$title}...\n";
}
