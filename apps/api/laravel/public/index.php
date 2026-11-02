<?php

/**
 * index.php — the web entry point: every HTTP request to the API starts here.
 *
 * Why: the web server's document root is public/ (api.libbynaz.org → …/api/public), so
 * only this file and .htaccess are reachable from the web; the code, .env and storage
 * stay outside it (CLAUDE.md: Laravel code lives outside public_html).
 *
 * How it connects: .htaccess sends every request that isn't a real file here. It checks
 * for maintenance mode, loads Composer's autoloader, builds the app from bootstrap/app.php
 * and lets it handle the request. Unchanged from the Laravel template apart from this
 * header and strict types.
 */

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
