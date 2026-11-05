<?php

/**
 * logging.php — sends everything Laravel logs to the core's JsonLogger.
 *
 * Why: the logging port (DOMAIN-MODEL §6) writes one JSON line per entry, redacted and
 * tagged with the request's reference code, to storage/logs/chapelcore-YYYY-MM-DD.log.
 * Using it as Laravel's only channel means Log::…, report() and the exception handler
 * all go through the same redaction; nothing reaches a log file any other way.
 *
 * Browser error reports get their own channel, so their entries are named
 * diagnostics.client (DOMAIN-MODEL §5.8) and can be told apart from the API's own.
 *
 * How it connects: Laravel merges this file with its own config/logging.php, channel by
 * channel. The "custom" driver calls App\Logging\CreateJsonLogger with each array;
 * LOG_CHANNEL in .env can still pick another default channel for local debugging.
 * ClientErrorController writes to client_errors (a Laravel config key can't contain a
 * dot, so the key differs from the entry's channel name).
 */

declare(strict_types=1);

use App\Logging\CreateJsonLogger;

return [
    'default' => env('LOG_CHANNEL', 'chapelcore'),

    'channels' => [
        'chapelcore' => [
            'driver' => 'custom',
            'via' => CreateJsonLogger::class,
            'path' => storage_path('logs'),
            'channel' => 'app',
        ],
        'client_errors' => [
            'driver' => 'custom',
            'via' => CreateJsonLogger::class,
            'path' => storage_path('logs'),
            'channel' => 'diagnostics.client',
        ],
    ],
];
