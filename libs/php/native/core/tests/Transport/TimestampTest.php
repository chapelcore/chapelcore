<?php

/**
 * TimestampTest.php — tests how instants are written in transport JSON.
 *
 * Why: DOMAIN-MODEL §2 (model 0.4): ChapelCore producers always write UTC with a
 * 3-digit millisecond fraction and a `Z`, e.g. `2026-10-21T15:04:05.123Z`, never an
 * offset such as `+00:00`. Log lines written within the same second still sort in order.
 *
 * How it connects: tests src/Transport/Timestamp.php, which the Serializer uses for
 * LogEntry.occurredAt (and for every Timestamp field after it).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Transport;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Transport\Timestamp;
use DateTimeImmutable;
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
}
