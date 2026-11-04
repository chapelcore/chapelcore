<?php

/**
 * api.php — every route the API serves.
 *
 * Why: the API serves only the routes we write (no web routes, no Laravel /up). Paths
 * are written relative to {apiBase}: bootstrap/app.php adds the prefix from
 * CHAPELCORE_API_PREFIX, so this one file serves both api.example.org/health and
 * example.org/api/health (PLAN D16). Phase 1 has no sessions or cookies, so no
 * middleware group.
 *
 * How it connects: loaded by bootstrap/app.php; checkpoint 4 adds
 * POST /diagnostics/client-errors.
 */

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
