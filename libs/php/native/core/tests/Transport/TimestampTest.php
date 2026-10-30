<?php

/**
 * TimestampTest.php — tests how instants are written and read in transport JSON.
 *
 * Why: DOMAIN-MODEL §2 (model 0.4): ChapelCore producers always write UTC with a
 * 3-digit millisecond fraction and a `Z`, e.g. `2026-10-21T15:04:05.123Z`, never an
 * offset such as `+00:00`. Log lines written within the same second still sort in order.
 *
 * Reading accepts only that contract shape (a 3- or 6-digit fraction is optional) and
 * refuses impossible dates instead of letting PHP roll them into the next month.
 *
 * How it connects: tests src/Transport/Timestamp.php, which the Serializer uses for
 * LogEntry.occurredAt (and for every Timestamp field after it); ClientErrorReportParser
 * reads ClientErrorReport.occurredAt with it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Transport;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Transport\Timestamp;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function instants(): iterable
    {
        yield 'UTC with milliseconds' => ['2026-10-21T15:04:05.123Z', '2026-10-21T15:04:05.123Z'];
        yield 'whole second gets .000' => ['2026-10-21T15:04:05Z', '2026-10-21T15:04:05.000Z'];
        yield 'an offset is converted to UTC' => ['2026-10-21T09:04:05.123-06:00', '2026-10-21T15:04:05.123Z'];
        yield 'microseconds are cut, not rounded' => ['2026-10-21T15:04:05.123999Z', '2026-10-21T15:04:05.123Z'];
    }

    #[DataProvider('instants')]
    public function testWritesUtcWithMilliseconds(string $instant, string $expected): void
    {
        self::assertSame($expected, Timestamp::format(new DateTimeImmutable($instant)));
    }

    public function testMatchesTheTimestampSchema(): void
    {
        $json = json_encode(Timestamp::format(new DateTimeImmutable()), JSON_THROW_ON_ERROR);

        self::assertSame([], (new ContractValidator())->errorsInJson($json, 'timestamp'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function timestampsToRead(): iterable
    {
        yield 'whole seconds' => ['2026-10-21T15:04:05Z', '2026-10-21T15:04:05.000000'];
        yield 'milliseconds' => ['2026-10-21T15:04:05.123Z', '2026-10-21T15:04:05.123000'];
        yield 'microseconds' => ['2026-10-21T15:04:05.123456Z', '2026-10-21T15:04:05.123456'];
        yield 'leap day' => ['2028-02-29T00:00:00Z', '2028-02-29T00:00:00.000000'];
    }

    /** @return iterable<string, array{string}> */
    public static function notTimestamps(): iterable
    {
        yield 'empty' => [''];
        yield 'an offset instead of Z' => ['2026-10-21T09:04:05.123-06:00'];
        yield 'no time zone' => ['2026-10-21T15:04:05'];
        yield 'lowercase z' => ['2026-10-21T15:04:05z'];
        yield 'space instead of T' => ['2026-10-21 15:04:05Z'];
        yield 'two-digit fraction' => ['2026-10-21T15:04:05.12Z'];
        yield 'nine-digit fraction' => ['2026-10-21T15:04:05.123456789Z'];
        yield 'date only' => ['2026-10-21'];
        yield '30 February' => ['2026-02-30T00:00:00Z'];
        yield '29 February in a common year' => ['2026-02-29T00:00:00Z'];
        yield 'month 13' => ['2026-13-01T00:00:00Z'];
        yield 'hour 24' => ['2026-10-21T24:00:00Z'];
        yield 'minute 60' => ['2026-10-21T15:60:00Z'];
        yield 'trailing newline' => ["2026-10-21T15:04:05Z\n"];
    }

    #[DataProvider('timestampsToRead')]
    public function testReadsTheContractShapeAsUtc(string $timestamp, string $expected): void
    {
        $instant = Timestamp::parse($timestamp);

        self::assertSame([$expected, 'UTC'], [$instant->format('Y-m-d\TH:i:s.u'), $instant->getTimezone()->getName()]);
    }

    #[DataProvider('notTimestamps')]
    public function testRejectsAnythingElse(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Timestamp::parse($value);
    }

    public function testReadingThenWritingGivesTheSameMilliseconds(): void
    {
        self::assertSame('2026-10-21T15:04:05.123Z', Timestamp::format(Timestamp::parse('2026-10-21T15:04:05.123Z')));
    }
}
