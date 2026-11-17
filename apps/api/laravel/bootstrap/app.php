<?php

/**
 * app.php — builds the Laravel application: its routes, middleware and error handling.
 *
 * Why: since Laravel 11 this one file replaces the old HTTP and console Kernel classes.
 * The template's web routes and its /up health route are removed: the API serves only
 * the routes we write (GET /health and POST /diagnostics/client-errors, from
 * checkpoints 3 and 4).
 *
 * How it connects: required by public/index.php (web requests) and artisan (commands).
 * The routes come from routes/api.php, under the prefix in config/chapelcore.php;
 * commands are found in app/Console/Commands (Laravel's default).
 */

declare(strict_types=1);

use App\Exceptions\RenderAsErrorEnvelope;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function (): void {
        // loaded after config is read, so the prefix can come from .env
        Route::prefix(config()->string('chapelcore.api_prefix'))->group(base_path('routes/api.php'));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // run AssignRequestId first, before every other middleware
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // always JSON, rendered by RenderAsErrorEnvelope
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
        $exceptions->render(fn (Throwable $e) => app(RenderAsErrorEnvelope::class)($e));
    })->create();
