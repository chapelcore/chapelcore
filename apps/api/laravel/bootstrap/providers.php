<?php

/**
 * providers.php — the list of the app's own service providers.
 *
 * Why: a service provider is where an app tells Laravel how to build its objects
 * (for us, the core's classes and their Laravel adapters, from checkpoint 2).
 *
 * How it connects: Laravel loads every provider listed here at startup, after the
 * framework's own.
 */

declare(strict_types=1);

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
];
