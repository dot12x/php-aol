<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Aol\Aol;
use Aol\Http;
use Aol\Http\Attribute\BaseUrl;
use Aol\Http\Attribute\Get;
use Aol\Http\Attribute\Headers;
use Aol\Http\Attribute\Path;
use Aol\Http\Attribute\Query;
use Aol\Http\Response;

#[BaseUrl('https://api.github.com')]
#[Headers(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'php-aol-example'])]
interface GitHubApi
{
    #[Get('/users/{login}')]
    public function user(#[Path] string $login): Response;

    #[Get('/users/{login}/repos')]
    public function repos(#[Path] string $login, #[Query] int $per_page = 5): Response;
}

echo "== declarative GitHub API demo ==\n";
echo "Attaching attributes to an interface turns it into an HTTP client.\n";
echo "Inside a scope the user and repos calls run in parallel.\n\n";

$gh = Http::fromInterface(GitHubApi::class);
$login = 'octocat';

$t0 = \microtime(true);

[$user, $repos] = Aol::scope(function () use ($gh, $login) {
    $u = Aol::async(static fn () => $gh->user($login));
    $r = Aol::async(static fn () => $gh->repos($login, per_page: 3));
    return [$u, $r];
});

$elapsed = \round((\microtime(true) - $t0) * 1000);

$userData = $user->json();
\assert(\is_array($userData));
echo "user: @{$userData['login']}";
if (isset($userData['name'])) {
    echo " ({$userData['name']})";
}
echo "\n";
echo "public repos: " . ($userData['public_repos'] ?? '?') . "\n";

$repoList = $repos->json();
\assert(\is_array($repoList));
\usort($repoList, static fn ($a, $b) => ($b['stargazers_count'] ?? 0) <=> ($a['stargazers_count'] ?? 0));

echo "\ntop 3 by stars:\n";
foreach (\array_slice($repoList, 0, 3) as $repo) {
    $stars = $repo['stargazers_count'] ?? 0;
    echo "  - {$repo['full_name']}  (★{$stars})\n";
}

echo "\n  both requests in parallel: {$elapsed}ms\n";
