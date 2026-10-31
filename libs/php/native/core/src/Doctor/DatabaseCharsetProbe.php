<?php

/**
 * DatabaseCharsetProbe.php — the doctor's `database.charset` check.
 *
 * Why: the host's default character set is latin1, which silently mangles names such as
 * "Zoë" and loses emoji; ChapelCore needs utf8mb4 everywhere (UTF8 on PostgreSQL)
 * (CLAUDE.md; DOMAIN-MODEL §5.8; hosting runbook §6). Every wrong table is named. The
 * doctor runs before migrations, so a database with no tables yet is fine.
 *
 * How it connects: one of the doctor's probes; it reads the DatabaseCharsets port.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class DatabaseCharsetProbe implements DoctorProbe
{
    public function __construct(
        private DatabaseDriver $driver,
        private DatabaseCharsets $charsets,
    ) {}

    public function name(): string
    {
        return 'database.charset';
    }

    public function check(): DoctorCheck
    {
        $expected = $this->driver->charset();
        $database = $this->charsets->database();
        $tables = $this->charsets->tables();

        $problems = [];
        if (strcasecmp($database, $expected) !== 0) {
            $problems[] = "the database uses {$database}, not {$expected}";
        }
        $wrongTables = [];
        foreach ($tables as $table => $charset) {
            if (strcasecmp($charset, $expected) !== 0) {
                $wrongTables[] = "{$table} ({$charset})";
            }
        }
        if ($wrongTables !== []) {
            $problems[] = "tables not using {$expected}: " . implode(', ', $wrongTables);
        }

        if ($problems === []) {
            $count = count($tables);
            $found = match ($count) {
                0 => 'the database (no tables yet)',
                1 => 'the database and 1 table',
                default => "the database and {$count} tables",
            };

            return new DoctorCheck($this->name(), Status::Ok, "{$expected}: {$found}", null);
        }

        return new DoctorCheck(
            $this->name(),
            Status::Down,
            ucfirst(implode('; ', $problems)),
            "Convert the database to {$expected}; see the hosting runbook §6.",
        );
    }
}
