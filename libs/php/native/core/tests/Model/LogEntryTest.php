<?php

/**
 * LogEntryTest.php — tests one line of the server's JSON log (DOMAIN-MODEL §5.8).
 *
 * Why: a reference code shown to a person must find exactly one log entry, so every
 * entry has a valid id, a channel such as `app` or `diagnostics.client`, and a
 * non-empty message. The entry itself doesn't redact: whatever builds it does
 * (checkpoint 4b), and the Serializer writes it as it is.
 *
 * How it connects: tests src/Model/LogEntry.php; SerializerTest checks it against
 * log-entry.schema.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\LogEntry;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Tests\Examples;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogEntryTest extends TestCase
{
    public function testHoldsItsFields(): void
    {
        $entry = Examples::logEntry();

        self::assertSame(
            ['0199e5a2-7c3b-7d4e-9f1a-2b3c4d5e6f70', LogLevel::Error, 'app', 'Unhandled exception', '7F3K-92QD'],
            [$entry->id, $entry->level, $entry->channel, $entry->message, $entry->requestId?->value],
        );
        self::assertSame(['route' => 'health', 'password' => '[redacted]'], $entry->context);
    }

    public function testOutsideARequestHasNoRequestIdContextOrException(): void
    {
        $entry = new LogEntry(
            '3f2b8c1e-9d4a-4f6b-8e2c-1a7d5b9e0c3f',
            new DateTimeImmutable('2026-10-21T15:05:00Z'),
            LogLevel::Info,
            'scheduler',
            'Heartbeat recorded',
            null,
            [],
            null,
        );

        self::assertSame([null, [], null], [$entry->requestId, $entry->context, $entry->exception]);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function brokenRules(): iterable
    {
        yield 'id not a UUID' => ['0199e5a2-7c3b-7d4e-9f1a', 'app', 'Unhandled exception'];
        yield 'id in uppercase' => ['0199E5A2-7C3B-7D4E-9F1A-2B3C4D5E6F70', 'app', 'Unhandled exception'];
        yield 'channel not a key' => ['0199e5a2-7c3b-7d4e-9f1a-2b3c4d5e6f70', 'App', 'Unhandled exception'];
        yield 'empty message' => ['0199e5a2-7c3b-7d4e-9f1a-2b3c4d5e6f70', 'app', ''];
    }

    #[DataProvider('brokenRules')]
    public function testRejectsABrokenRule(string $id, string $channel, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LogEntry($id, new DateTimeImmutable(), LogLevel::Error, $channel, $message, null, [], null);
    }
}
