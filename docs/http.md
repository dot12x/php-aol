# HTTP

The HTTP module has two modes: a static facade for one-off requests and a declarative interface for structured API clients.

Underlying transport: `amphp/http-client`. Never appears in public signatures.

For WebSocket support, see [`websocket.md`](websocket.md). Server-Sent Events live below.

---

## Static facade

```php
<?php

use Aol\Http;

$resp = Http::get('https://api.example.com/users/42');
```

Every method suspends the current Fiber until the response arrives — the event loop continues running other work. From the caller's perspective it looks synchronous.

### Methods

```php
Http::get(string $url, array $headers = []): Response
Http::post(string $url, mixed $json = null, array $headers = []): Response
Http::put(string $url, mixed $json = null, array $headers = []): Response
Http::patch(string $url, mixed $json = null, array $headers = []): Response
Http::delete(string $url, array $headers = []): Response
```

### `Response`

| Member | Type | Description |
|---|---|---|
| `$resp->status` | `int` | HTTP status code |
| `$resp->body` | `string` | Raw response body |
| `$resp->ok` | `bool` | `true` when status is 200–299 (property hook) |
| `$resp->contentType` | `string` | `Content-Type` header value |
| `$resp->json()` | `mixed` | `json_decode` of body |
| `$resp->as(string $class)` | `object` | Decode body into a class (see below) |
| `$resp->header(string $name)` | `string\|null` | Single response header |
| `$resp->headers()` | `array` | All response headers |

```php
<?php

use Aol\Http;

$resp = Http::get('https://api.example.com/users/42');

if ($resp->ok) {
    $user = $resp->as(User::class);
}
```

`$resp->as($class)` decoding rules:
1. If the class has a static `fromArray(array): static` method, that is called with `json_decode` output.
2. If the class has a single-argument constructor accepting an array, that is used.
3. Otherwise a `RuntimeException` is thrown.

---

## Declarative interface

Define a plain PHP interface annotated with HTTP attributes, then get a proxy via `Http::fromInterface()`.

```php
<?php

use Aol\Http;
use Aol\Http\Attribute\{BaseUrl, Headers, Get, Post, Delete, Path, Query, Body, Header};
use Aol\Attribute\{Retry, Timeout};

#[BaseUrl('https://api.github.com')]
#[Headers(['Accept' => 'application/vnd.github+json'])]
interface GitHubApi
{
    #[Get('/users/{login}')]
    #[Retry(times: 3, on: [NetworkException::class])]
    #[Timeout(10)]
    public function getUser(#[Path] string $login): User;

    #[Get('/users/{login}/repos')]
    public function listRepos(
        #[Path] string $login,
        #[Query] string $type = 'all',
        #[Query] int $per_page = 30,
    ): array;

    #[Post('/user/repos')]
    public function createRepo(
        #[Body] CreateRepoRequest $req,
        #[Header('Authorization')] string $token,
    ): Repo;

    #[Delete('/repos/{owner}/{repo}')]
    public function deleteRepo(
        #[Path] string $owner,
        #[Path] string $repo,
    ): void;
}

$gh = Http::fromInterface(GitHubApi::class);

$user = Aol::scope(fn() => $gh->getUser('alice'));
```

### Return type decoding

| Return type | Behavior |
|---|---|
| `Aol\Http\Response` | Raw response object, no decoding |
| `array` or `mixed` | `json_decode($body, true)` |
| `void` | Response discarded |
| Any other class | Same rules as `$resp->as($class)` above |

### Parameter attributes

| Attribute | Where | Effect |
|---|---|---|
| `#[Path]` | method parameter | Fills `{paramName}` in the path |
| `#[Query]` | method parameter | Appended to query string |
| `#[Body]` | method parameter | JSON-encoded as request body |
| `#[Header(string $name)]` | method parameter | Sent as a request header |

---

## Testing

Inject a stub `HttpClient` to avoid real network calls:

```php
<?php

use Aol\Http;
use Amphp\Http\Client\HttpClient;
use Amphp\Http\Client\ApplicationInterceptor;
use Amphp\Http\Client\DelegateHttpClient;
use Amphp\Http\Client\Request;
use Amphp\Http\Client\Response;

class StubClient implements HttpClient
{
    public function request(Request $request, Cancellation $cancellation): Response
    {
        // return a crafted Response for your test
    }
}

Http::useClient(new StubClient());

// All Http::get/post/fromInterface calls now go through StubClient
$result = Aol::scope(fn() => Http::get('https://api.example.com/test'));
```

`Http::useClient()` is global — reset it in `tearDown` if you set it per test.

---

## Server-Sent Events (SSE)

Three surfaces, same `Aol\Http\Sse\SseEvent` value type.

```php
final readonly class SseEvent
{
    public string  $event = 'message';
    public string  $data  = '';
    public ?string $id    = null;
    public ?int    $retry = null;  // milliseconds — W3C wire value (sole AOL exception to the "seconds" rule)
}
```

### 1. Imperative — `Http::sse()`

```php
use Aol\Aol;
use Aol\Http;

Aol::scope(function () {
    foreach (Http::sse('https://api.example.com/events') as $event) {
        echo "[{$event->event}] {$event->data}\n";
    }
});
```

`Http::sse()` returns an `Aol\Http\Sse\SseStream` (`IteratorAggregate<int, SseEvent>`). The iterator drives chunked reads off the live response body and closes it when iteration ends. Must run inside `Aol::scope()` — the scope owns the body lifetime.

### 2. Declarative on an HTTP interface — `#[SseStream]`

```php
use Aol\Http;
use Aol\Http\Attribute\{BaseUrl, Get, Query, SseStream};
use Aol\Http\Sse\SseEvent;

#[BaseUrl('https://api.example.com')]
interface MarketFeed
{
    /** @return iterable<int, SseEvent> */
    #[Get('/ticks')]
    #[SseStream]
    public function ticks(#[Query] string $symbol): iterable;
}

$feed = Http::fromInterface(MarketFeed::class);
Aol::scope(function () use ($feed) {
    foreach ($feed->ticks('BTCUSD') as $event) { /* SseEvent */ }
});
```

The proxy detects `#[SseStream]` and returns an `SseStream` directly instead of decoding the body.

### 3. Declarative on a wrapped class — `#[OnSse]`

```php
use Aol\Aol;
use Aol\Attribute\Worker;
use Aol\Http\Attribute\OnSse;
use Aol\Http\Sse\SseEvent;

#[Worker]
class Ingestor
{
    #[OnSse('https://api.example.com/events')]
    public function rx(SseEvent $event): void { /* per-event side effect */ }
}

Aol::scope(fn() => Aol::wrap(Ingestor::class));
```

`#[OnSse]` is repeatable: a single method may subscribe to multiple URLs, and a class may have several `#[OnSse]` handlers. Each subscription runs in its own background fiber.
