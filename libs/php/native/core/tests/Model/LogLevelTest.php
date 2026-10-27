<?php

/**
 * LogLevelTest.php — tests the eight log levels.
 *
 * Why: LogEntry.level uses the PSR-3 / RFC 5424 levels (DOMAIN-MODEL §5.8). The enum
 * must offer exactly the values the contract schema allows, from least to most severe,
 * and they must be PSR-3's own level strings, so JsonLogger can read any PSR-3 call.
 *
 * How it connects: tests src/Model/LogLevel.php against log-entry.schema.json and
 * psr/log's LogLevel constants.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Model\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;
use ReflectionClass;

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

    public function testHasExactlyPsr3sLevels(): void
    {
        $psrLevels = array_values((new ReflectionClass(PsrLogLevel::class))->getConstants());
        $ours = array_map(static fn(LogLevel $level): string => $level->value, LogLevel::cases());

        self::assertEqualsCanonicalizing($psrLevels, $ours);
    }
}
