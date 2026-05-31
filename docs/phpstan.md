# PHPStan

php-aol ships an in-tree PHPStan extension at `ext/phpstan/`. The extension teaches PHPStan about the library's magic — without it, calls through `Wrapper` and `Pending` proxies produce type errors and "method not found" errors.

---

## What the extension covers

| Extension class | What it does |
|---|---|
| `WrapperMethodsExtension` | Narrows `Wrapper<T>::__call` return types to `Pending<MethodReturn>` based on the wrapped class's method signatures |
| `PendingMethodsExtension` | Allows `__call` chaining on `Pending<T>` — calling a method on a Pending returns another `Pending` typed to the chained method's return |
| `PendingPropertiesExtension` | Allows `__get` on `Pending<T>` — property access returns a `Pending` typed to the property's type |
| `ProxyInstanceMethodsExtension` | Makes `Http::fromInterface(MyApi::class)` return a type that satisfies the `MyApi` interface for PHPStan |

Without the extension, PHPStan will flag `$wrapped->method()` as "Call to an undefined method" and `$pending->property` as "Access to an undefined property."

---

## Auto-setup (recommended)

If you have `phpstan/extension-installer` in your project, the extension is wired automatically. No manual config needed.

```bash
composer require --dev phpstan/extension-installer
```

The library's `composer.json` declares the extension path in `extra.phpstan.includes`, which `extension-installer` picks up.

---

## Manual setup

If you do not use `extension-installer`, add the extension to your `phpstan.neon`:

```neon
includes:
    - vendor/php-aol/php-aol/ext/phpstan/extension.neon
```

---

## Verifying the extension is active

Run PHPStan on a file that calls through a wrapped class:

```php
<?php

use Aol\Aol;
use Aol\Attribute\Async;
use Aol\Pending;

class Fetcher
{
    #[Async]
    public function fetch(string $url): string { /* ... */ }
}

$f = Aol::wrap(Fetcher::class);
$p = $f->fetch('https://example.com');

/** @var Pending<string> $p — PHPStan should infer this with the extension active */
```

With the extension active, `$p` is typed as `Pending<string>` and no errors are emitted. Without it, you will see a "Call to an undefined method" error.

---

## PHPStan level

The library itself is clean at **PHPStan level 9**. Consumer projects are free to run at any level they choose.
