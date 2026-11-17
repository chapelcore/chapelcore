<?php

/**
 * MariaDbCharsets.php — reads the character sets of the database and each of its tables
 * from MariaDB, for the doctor's database.charset check.
 *
 * Why: the host creates databases in latin1 by default, and latin1 can't store every
 * character (CLAUDE.md security rules: utf8mb4 everywhere). The connection being utf8mb4
 * isn't enough: a database or table made in latin1 stays latin1, so the doctor asks
 * MariaDB itself, through information_schema.
 *
 * How it connects: the Laravel adapter for the core's DatabaseCharsets port, bound in
 * AppServiceProvider; the core's DatabaseCharsetProbe decides what the answers mean.
 * PostgreSQL gets its own adapter in Phase 3 (PLAN D20).
 */

declare(strict_types=1);

namespace App\Doctor;

use ChapelCore\Core\Doctor\DatabaseCharsets;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class MariaDbCharsets implements DatabaseCharsets
{
    public function __construct(private ConnectionInterface $database) {}

    public function database(): string
    {
        // database() is the one this connection uses (DB_DATABASE)
        $charset = $this->database->scalar(
            'select default_character_set_name from information_schema.schemata where schema_name = database()',
        );

        return is_string($charset) ? $charset : throw new RuntimeException('Could not read the database character set');
    }

    /** @return array<string, string> */
    public function tables(): array
    {
        // a table stores its collation (utf8mb4_unicode_ci); the join finds the character
        // set that collation belongs to (utf8mb4). Views have no character set of their own.
        $rows = $this->database->select(
            'select t.table_name as name, c.character_set_name as charset'
            .' from information_schema.tables t'
            .' join information_schema.collation_character_set_applicability c on c.collation_name = t.table_collation'
            ." where t.table_schema = database() and t.table_type = 'BASE TABLE'"
            .' order by t.table_name',
        );

        $tables = [];
        foreach ($rows as $row) {
            ['name' => $name, 'charset' => $charset] = (array) $row + ['name' => null, 'charset' => null];
            if (is_string($name) && is_string($charset)) {
                $tables[$name] = $charset;
            }
        }

        return $tables;
    }
}
