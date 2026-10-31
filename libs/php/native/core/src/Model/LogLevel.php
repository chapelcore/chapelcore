<?php

/**
 * LogLevel.php — the eight log levels, least to most severe (DOMAIN-MODEL §5.8).
 *
 * Why: LogEntry.level uses the PSR-3 / RFC 5424 levels, written in lowercase, exactly
 * as log-entry.schema.json lists them.
 *
 * How it connects: LogEntry holds one; the logger (checkpoint 4b) turns PSR-3's level
 * strings into these with LogLevel::from().
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';
}
