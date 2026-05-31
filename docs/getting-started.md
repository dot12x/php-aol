# Getting Started

## Requirements

- PHP 8.4+
- Composer

## Install

```bash
composer require php-aol/php-aol
```

## First scope

A scope is an arena that runs async work and resolves all results when it closes.

```php
<?php

use Aol\Aol;

$result = Aol::scope(function () {
    return 42;
});

var_dump($result); // int(42)
```

The scope can return any value. Pending values returned from inside are resolved before the scope hands back control.

## First wrapped class

```php
<?php

use Aol\Aol;
use Aol\Attribute\Async;

class ImageProcessor
{
    #[Async]
    public function resize(string $path, int $width): string
    {
        // I/O work here — suspends the Fiber, other work runs
        return processedPath($path, $width);
    }

    #[Async]
    public function compress(string $path): string
    {
        return compressedPath($path);
    }
}

$proc = Aol::wrap(ImageProcessor::class);

[$a, $b] = Aol::scope(function () use ($proc) {
    $resized   = $proc->resize('photo.jpg', 800);   // Pending<string>
    $thumbnail = $proc->resize('photo.jpg', 200);   // Pending<string>, parallel
    $compressed = $proc->compress($resized);         // Pending<string>, waits for $resized
    return [$compressed, $thumbnail];               // scope resolves both
});
```

## What just happened

A scope is an arena: every async call inside it is tracked, and the scope waits for all of them before returning. `Aol::wrap()` animates a class — it creates an instance and returns a proxy that intercepts calls to `#[Async]` methods. Those methods return a `Pending` instead of the real value; passing a `Pending` into another async call declares a dependency, and the library schedules work automatically. When the scope closes, every `Pending` resolves (or the scope surfaces the error).

## Where to go next

- [concepts.md](./concepts.md) — understand the four primitives and the mental model
- [attributes.md](./attributes.md) — full attribute reference
