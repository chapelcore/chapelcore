<?php

/**
 * DatabaseDriver.php — the databases ChapelCore runs on.
 *
 * Why: MariaDB is the default and PostgreSQL is supported through configuration
 * (CLAUDE.md, Stack). Rules that differ by database, such as which PDO extension PHP
 * needs (and in checkpoint 5b the expected character set), live here, once. The values
 * are Laravel's connection driver names.
 *
 * How it connects: the doctor's php.extensions check asks for pdoExtension(); Laravel
 * (1.5) maps its configured connection to one of these.
 */

declare(strict_types=1);

namespace ChapelCore\Core;

enum DatabaseDriver: string
{
    case MariaDb = 'mariadb';
    case PostgreSql = 'pgsql';

    /** The PHP extension PDO needs to talk to this database. */
    public function pdoExtension(): string
    {
        return match ($this) {
            self::MariaDb => 'pdo_mysql',
            self::PostgreSql => 'pdo_pgsql',
        };
    }
}
