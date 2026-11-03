<?php

/**
 * logging.php — sends everything Laravel logs to the core's JsonLogger.
 *
 * Why: the logging port (DOMAIN-MODEL §6) writes one JSON line per entry, redacted and
 * tagged with the request's reference code, to storage/logs/chapelcore-YYYY-MM-DD.log.
 * Using it as Laravel's only channel means Log::…, report() and the exception handler
 * all go through the same redaction; nothing reaches a log file any other way.
 *
 * How it connects: Laravel merges this file with its own config/logging.php, channel by
 * channel. The "custom" driver calls App\Logging\CreateJsonLogger with this array;
 * LOG_CHANNEL in .env can still pick another channel for local debugging.
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
    ],
];
