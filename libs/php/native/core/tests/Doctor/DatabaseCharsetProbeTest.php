<?php

/**
 * DatabaseCharsetProbeTest.php — tests the doctor's `database.charset` check.
 *
 * Why: the host's default character set is latin1, which silently mangles names such as
 * "Zoë" and loses emoji; ChapelCore needs utf8mb4 everywhere (CLAUDE.md; DOMAIN-MODEL
 * §5.8; hosting runbook §6). PostgreSQL's equivalent is UTF8. Every table that is wrong
 * is named. The doctor runs before migrations, so a new, empty database is fine.
 *
 * How it connects: tests src/Doctor/DatabaseCharsetProbe.php with a stand-in for the
 * DatabaseCharsets port, which Laravel (1.5) answers from the real database.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Doctor\DatabaseCharsetProbe;
use ChapelCore\Core\Doctor\DatabaseCharsets;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DatabaseCharsetProbeTest extends TestCase
{
    /** @return iterable<string, array{DatabaseDriver, string, array<string, string>, string}> */
    public static function correct(): iterable
    {
        yield 'MariaDB with tables' => [DatabaseDriver::MariaDb, 'utf8mb4', ['events' => 'utf8mb4', 'people' => 'utf8mb4'], 'utf8mb4: the database and 2 tables'];
        yield 'MariaDB with one table' => [DatabaseDriver::MariaDb, 'utf8mb4', ['events' => 'utf8mb4'], 'utf8mb4: the database and 1 table'];
        yield 'a new database before migrations' => [DatabaseDriver::MariaDb, 'utf8mb4', [], 'utf8mb4: the database (no tables yet)'];
        yield 'PostgreSQL' => [DatabaseDriver::PostgreSql, 'UTF8', ['events' => 'UTF8'], 'UTF8: the database and 1 table'];
        yield 'any letter case' => [DatabaseDriver::MariaDb, 'UTF8MB4', ['events' => 'Utf8mb4'], 'utf8mb4: the database and 1 table'];
    }

    /** @param array<string, string> $tables */
    #[DataProvider('correct')]
    public function testTheRightCharacterSetIsOk(DatabaseDriver $driver, string $database, array $tables, string $message): void
    {
        $check = (new DatabaseCharsetProbe($driver, self::charsets($database, $tables)))->check();

        self::assertSame(['database.charset', Status::Ok, $message, null], [$check->name, $check->status, $check->message, $check->fix]);
    }

    public function testALatin1DatabaseIsDownWithTheFix(): void
    {
        $check = (new DatabaseCharsetProbe(DatabaseDriver::MariaDb, self::charsets('latin1', [])))->check();

        self::assertSame(
            [Status::Down, 'The database uses latin1, not utf8mb4', 'Convert the database to utf8mb4; see the hosting runbook §6.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testEveryWrongTableIsNamed(): void
    {
        $tables = ['events' => 'latin1', 'notes' => 'utf8mb4', 'people' => 'utf8mb3'];

        $check = (new DatabaseCharsetProbe(DatabaseDriver::MariaDb, self::charsets('utf8mb4', $tables)))->check();

        self::assertSame([Status::Down, 'Tables not using utf8mb4: events (latin1), people (utf8mb3)'], [$check->status, $check->message]);
    }

    public function testAWrongDatabaseAndWrongTablesAreBothReported(): void
    {
        $check = (new DatabaseCharsetProbe(DatabaseDriver::PostgreSql, self::charsets('SQL_ASCII', ['events' => 'SQL_ASCII'])))->check();

        self::assertSame(
            [Status::Down, 'The database uses SQL_ASCII, not UTF8; tables not using UTF8: events (SQL_ASCII)', 'Convert the database to UTF8; see the hosting runbook §6.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    /** @param array<string, string> $tables */
    private static function charsets(string $database, array $tables): DatabaseCharsets
    {
        return new readonly class ($database, $tables) implements DatabaseCharsets {
            /** @param array<string, string> $tables */
            public function __construct(private string $database, private array $tables) {}

            public function database(): string
            {
                return $this->database;
            }

            public function tables(): array
            {
                return $this->tables;
            }
        };
    }
}
