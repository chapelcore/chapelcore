<?php

/**
 * Examples.php — ready-made model objects for the core's tests.
 *
 * Why: many tests need a valid ResponseMeta or HealthStatus around the one thing they
 * check. Building them here keeps each test short and focused.
 *
 * How it connects: used by tests/Model and tests/Transport. The values match the
 * contract's valid fixtures where they overlap.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests;

use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\HealthStatus;
use ChapelCore\Core\Model\PageInfo;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Model\ResponseMeta;
use ChapelCore\Core\Model\ServedBy;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Model\VersionInfo;

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
}
