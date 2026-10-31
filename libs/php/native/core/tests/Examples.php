<?php

/**
 * Examples.php — ready-made model objects for the core's tests.
 *
 * Why: many tests need a valid ResponseMeta or HealthStatus around the one thing they
 * check. Building them here keeps each test short and focused.
 *
 * How it connects: used by tests/Model and tests/Transport. The values match the
 * contract's valid fixtures where they overlap; logEntry() and exceptionInfo() are
 * fixtures/valid/log-entry/error-with-exception.json and exception-info/*.json;
 * clientErrorReport() is fixtures/valid/client-error-report/admin.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests;

use ChapelCore\Core\Model\ClientApp;
use ChapelCore\Core\Model\ClientErrorReport;
use ChapelCore\Core\Model\ExceptionInfo;
use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\HealthStatus;
use ChapelCore\Core\Model\LogEntry;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Model\PageInfo;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Model\ResponseMeta;
use ChapelCore\Core\Model\ServedBy;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Model\VersionInfo;
use DateTimeImmutable;

final class Examples
{
    public static function refCode(): RefCode
    {
        return new RefCode('7F3K-92QD');
    }

    public static function servedBy(): ServedBy
    {
        return new ServedBy('laravel', '13.35.0', 'php', '8.5.11');
    }

    public static function version(): VersionInfo
    {
        return new VersionInfo('1.0.0', 'Kootenai');
    }

    public static function meta(?PageInfo $page = null): ResponseMeta
    {
        return new ResponseMeta(self::refCode(), self::servedBy(), $page);
    }

    public static function healthy(): HealthStatus
    {
        return new HealthStatus(
            self::version(),
            new HealthCheck('database', Status::Ok, null),
            new HealthCheck('scheduler', Status::Ok, null),
            new HealthCheck('storage', Status::Ok, null),
        );
    }

    public static function databaseDown(): HealthStatus
    {
        return new HealthStatus(
            self::version(),
            new HealthCheck('database', Status::Down, 'Database unavailable'),
            new HealthCheck('scheduler', Status::Ok, null),
            new HealthCheck('storage', Status::Ok, null),
        );
    }

    public static function exceptionInfo(?ExceptionInfo $previous = null): ExceptionInfo
    {
        return new ExceptionInfo(
            'RuntimeException',
            'Could not write the heartbeat file.',
            '/home/libby/chapelcore/app/Heartbeat.php',
            42,
            ['#0 /home/libby/chapelcore/app/Console/Kernel.php(18): write()', '#1 {main}'],
            $previous,
        );
    }

    public static function logEntry(): LogEntry
    {
        return new LogEntry(
            '0199e5a2-7c3b-7d4e-9f1a-2b3c4d5e6f70',
            new DateTimeImmutable('2026-10-21T15:04:05.123Z'),
            LogLevel::Error,
            'app',
            'Unhandled exception',
            self::refCode(),
            ['route' => 'health', 'password' => '[redacted]'],
            self::exceptionInfo(),
        );
    }

    public static function clientErrorReport(): ClientErrorReport
    {
        return new ClientErrorReport(
            ClientApp::Admin,
            '0.1.0',
            '/admin/status',
            "Cannot read properties of undefined (reading 'checks')",
            ['TypeError: Cannot read properties of undefined', '    at StatusPage (status.vue:42:13)'],
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
            new DateTimeImmutable('2026-10-21T15:04:05.123Z'),
        );
    }
}
