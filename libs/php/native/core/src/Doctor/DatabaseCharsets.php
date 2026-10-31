<?php

/**
 * DatabaseCharsets.php — the port the database.charset check reads from.
 *
 * Why: the check's rule is plain PHP, but finding a database's character sets needs a
 * live connection and a driver-specific query, which belongs to the framework.
 *
 * How it connects: Laravel (1.5) implements it with its database connection;
 * DatabaseCharsetProbe compares the answers with DatabaseDriver::charset(). If the
 * database can't be reached, the methods throw and the Doctor reports why.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

interface DatabaseCharsets
{
    /** The database's default character set, as the database names it (`utf8mb4`, `UTF8`). */
    public function database(): string;

    /** @return array<string, string> each table's character set, by table name; [] before migrations */
    public function tables(): array;
}
