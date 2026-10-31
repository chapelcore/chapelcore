<?php

/**
 * UuidGeneratorTest.php — tests the ids given to log entries.
 *
 * Why: LogEntry.id is a Uuid (DOMAIN-MODEL §5.8). Version 7 (RFC 9562) starts with the
 * time in milliseconds, so ids sort in the order entries were written, which keeps a
 * database index on them compact when logs move into the database (Phase 3). The
 * remaining 74 bits are secure random bytes. Known times and bytes must give known ids.
 *
 * How it connects: tests src/Diagnostics/UuidGenerator.php; LogEntryFactory passes the
 * entry's own time, so the id and occurredAt always agree.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Diagnostics\UuidGenerator;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UuidGeneratorTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function knownValues(): iterable
    {
        yield 'all zero bits' => ['2026-10-25T12:00:00Z', '00000000000000000000', '01a172ee-9200-7000-8000-000000000000'];
        yield 'all one bits' => ['2026-10-25T12:00:00Z', 'ffffffffffffffffffff', '01a172ee-9200-7fff-bfff-ffffffffffff'];
        yield 'mixed' => ['2026-10-25T12:00:00Z', '0123456789abcdef0123', '01a172ee-9200-7123-8567-89abcdef0123'];
        yield 'milliseconds count' => ['2026-10-25T12:00:00.123Z', '00000000000000000000', '01a172ee-927b-7000-8000-000000000000'];
        yield 'an offset is the same instant' => ['2026-10-25T06:00:00-06:00', '00000000000000000000', '01a172ee-9200-7000-8000-000000000000'];
    }

    #[DataProvider('knownValues')]
    public function testEncodesKnownTimesAndBytes(string $at, string $hex, string $expected): void
    {
        $generator = new UuidGenerator(static fn(int $length): string => (string) hex2bin($hex));

        self::assertSame($expected, $generator->generate(new DateTimeImmutable($at)));
    }

    public function testAsksForExactlyTenBytes(): void
    {
        $asked = [];
        $generator = new UuidGenerator(static function (int $length) use (&$asked): string {
            $asked[] = $length;

            return str_repeat("\0", $length);
        });

        $generator->generate(new DateTimeImmutable());

        self::assertSame([10], $asked);
    }

    public function testRejectsASourceThatGivesTheWrongNumberOfBytes(): void
    {
        $generator = new UuidGenerator(static fn(int $length): string => 'abcd');

        $this->expectException(LogicException::class);
        $generator->generate(new DateTimeImmutable());
    }

    public function testALaterMillisecondSortsLaterWhateverTheRandomBits(): void
    {
        $high = new UuidGenerator(static fn(int $length): string => str_repeat("\xff", $length));
        $low = new UuidGenerator(static fn(int $length): string => str_repeat("\0", $length));

        self::assertLessThan(
            $low->generate(new DateTimeImmutable('2026-10-25T12:00:00.001Z')),
            $high->generate(new DateTimeImmutable('2026-10-25T12:00:00.000Z')),
        );
    }

    public function testRealIdsAreValidAndDiffer(): void
    {
        $generator = new UuidGenerator();
        $now = new DateTimeImmutable();
        $ids = [];
        for ($i = 0; $i < 1000; $i++) {
            $ids[] = $generator->generate($now);
        }

        self::assertCount(1000, array_unique($ids));
        self::assertSame([], (new ContractValidator())->errorsInJson(json_encode($ids[0], JSON_THROW_ON_ERROR), 'uuid'));
    }
}
