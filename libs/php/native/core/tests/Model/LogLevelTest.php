<?php

/**
 * LogLevelTest.php — tests the eight log levels.
 *
 * Why: LogEntry.level uses the PSR-3 / RFC 5424 levels (DOMAIN-MODEL §5.8). The enum
 * must offer exactly the values the contract schema allows, from least to most severe.
 *
 * How it connects: tests src/Model/LogLevel.php against log-entry.schema.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Model\LogLevel;
use PHPUnit\Framework\TestCase;

final class LogLevelTest extends TestCase
{
    public function testHasTheSchemaLevelsInOrder(): void
    {
        /** @var array{properties: array{level: array{enum: list<string>}}} $schema */
        $schema = json_decode(
            (string) file_get_contents(ContractValidator::schemasDir() . '/log-entry.schema.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            $schema['properties']['level']['enum'],
            array_map(static fn(LogLevel $level): string => $level->value, LogLevel::cases()),
        );
    }
}
