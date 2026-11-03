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
 * Checkpoint 2 adds the request-ID middleware and the JSON-only error rendering here;
 * checkpoint 3 adds the routes.
 */

declare(strict_types=1);

use App\Exceptions\RenderAsErrorEnvelope;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function (Middleware $middleware): void {
        // run AssignRequestId first, before every other middleware
        $middleware->prepend(AssignRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // always JSON, rendered by RenderAsErrorEnvelope
        $exceptions->shouldRenderJsonWhen(fn (): bool => true);
        $exceptions->render(fn (Throwable $e) => app(RenderAsErrorEnvelope::class)($e));
    })->create();
