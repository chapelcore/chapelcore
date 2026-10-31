<?php

/**
 * LogEntryFactoryTest.php — tests how a PSR-3 log call becomes a LogEntry.
 *
 * Why: this is where the logging rules meet (DOMAIN-MODEL §5.8 and CLAUDE.md, Logging):
 * the message and context are always redacted, an exception under `exception` becomes
 * the entry's ExceptionInfo, and anything else in the context (dates, objects,
 * resources, very deep arrays) becomes plain JSON data first, so writing a log line
 * never fails and an object can't carry unredacted text past the Redactor.
 *
 * How it connects: tests src/Diagnostics/LogEntryFactory.php, which uses the
 * UuidGenerator, the Redactor and ExceptionInfo; JsonLogger calls it for every line.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Diagnostics\LogEntryFactory;
use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Diagnostics\UuidGenerator;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use ChapelCore\Core\Transport\Serializer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

final class LogEntryFactoryTest extends TestCase
{
    public function testTakesItsIdAndTimeFromTheSameMoment(): void
    {
        $entry = self::factory()->create(LogLevel::Info, 'scheduler', 'Heartbeat recorded', [], null);

        self::assertSame(
            ['01a172ee-9200-7000-8000-000000000000', '2026-10-25T12:00:00+00:00', LogLevel::Info, 'scheduler', null],
            [$entry->id, $entry->occurredAt->format('c'), $entry->level, $entry->channel, $entry->requestId],
        );
    }

    public function testKeepsTheRequestId(): void
    {
        $entry = self::factory()->create(LogLevel::Error, 'app', 'Unhandled exception', [], new RefCode('7F3K-92QD'));

        self::assertSame('7F3K-92QD', $entry->requestId?->value);
    }

    public function testRedactsTheMessage(): void
    {
        $entry = self::factory()->create(LogLevel::Warning, 'app', 'Login failed for ann@example.org', [], null);

        self::assertSame('Login failed for [email]', $entry->message);
    }

    public function testAcceptsAStringableMessage(): void
    {
        $message = new class implements Stringable {
            public function __toString(): string
            {
                return 'Call 406-293-1234';
            }
        };

        self::assertSame('Call [number]', self::factory()->create(LogLevel::Info, 'app', $message, [], null)->message);
    }

    public function testAnEmptyMessageIsWrittenAsNoMessage(): void
    {
        self::assertSame('(no message)', self::factory()->create(LogLevel::Info, 'app', '', [], null)->message);
    }

    public function testRedactsTheContext(): void
    {
        $entry = self::factory()->create(LogLevel::Info, 'app', 'Signed in', ['password' => 'hunter2', 'user' => 'ann@example.org'], null);

        self::assertSame(['password' => '[redacted]', 'user' => '[email]'], $entry->context);
    }

    public function testAnExceptionInTheContextBecomesTheEntrysException(): void
    {
        $context = ['exception' => new RuntimeException('Mail to ann@example.org bounced'), 'route' => 'health'];

        $entry = self::factory()->create(LogLevel::Error, 'app', 'Unhandled exception', $context, null);

        self::assertSame(['route' => 'health'], $entry->context);
        self::assertSame([RuntimeException::class, 'Mail to [email] bounced'], [$entry->exception?->type, $entry->exception?->message]);
    }

    public function testAnExceptionKeyThatIsNotAThrowableStaysInTheContext(): void
    {
        $entry = self::factory()->create(LogLevel::Error, 'app', 'Failed', ['exception' => 'Timeout for ann@example.org'], null);

        self::assertSame([['exception' => 'Timeout for [email]'], null], [$entry->context, $entry->exception]);
    }

    /** @return iterable<string, array{mixed, mixed}> */
    public static function contextValues(): iterable
    {
        yield 'a date becomes a Timestamp' => [new DateTimeImmutable('2026-10-21T09:04:05.123-06:00'), '2026-10-21T15:04:05.123Z'];
        yield 'a Stringable becomes redacted text' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return 'Mail to ann@example.org';
                }
            },
            'Mail to [email]',
        ];
        yield 'a backed enum becomes its value' => [Status::Degraded, 'degraded'];
        yield 'any other object becomes its class name' => [new stdClass(), '[object stdClass]'];
        yield 'a float that JSON cannot hold becomes text' => [NAN, 'NAN'];
        yield 'infinity becomes text' => [-INF, '-INF'];
        yield 'objects inside lists are converted too' => [[new stdClass(), 3], ['[object stdClass]', 3]];
    }

    #[DataProvider('contextValues')]
    public function testConvertsContextValuesToPlainData(mixed $value, mixed $expected): void
    {
        $entry = self::factory()->create(LogLevel::Info, 'app', 'Value', ['value' => $value], null);

        self::assertSame(['value' => $expected], $entry->context);
    }

    public function testAResourceBecomesAMarker(): void
    {
        $stream = fopen('php://memory', 'r');

        $entry = self::factory()->create(LogLevel::Info, 'app', 'Value', ['value' => $stream], null);

        self::assertSame(['value' => '[resource]'], $entry->context);
    }

    public function testAnotherExceptionInTheContextIsWrittenAsExceptionInfo(): void
    {
        $entry = self::factory()->create(LogLevel::Warning, 'app', 'Retrying', ['cause' => new RuntimeException('Timeout for ann@example.org')], null);

        $cause = $entry->context['cause'] ?? null;
        self::assertIsArray($cause);
        self::assertSame([RuntimeException::class, 'Timeout for [email]'], [$cause['type'] ?? null, $cause['message'] ?? null]);
        self::assertSame([], (new ContractValidator())->errorsInJson(json_encode($cause, JSON_THROW_ON_ERROR), 'exception-info'));
    }

    public function testVeryDeepContextIsCutOff(): void
    {
        $shallow = self::nested(5);
        $deep = self::nested(12);

        $entry = self::factory()->create(LogLevel::Info, 'app', 'Nested', ['shallow' => $shallow, 'deep' => $deep], null);
        $json = json_encode($entry->context, JSON_THROW_ON_ERROR);

        self::assertSame(1, substr_count($json, '"bottom"'), 'the shallow one is kept whole');
        self::assertStringContainsString('"[too deep]"', $json);
    }

    public function testTheEntryAlwaysMatchesTheSchema(): void
    {
        $context = [
            'exception' => new RuntimeException(),
            'at' => new DateTimeImmutable(),
            'object' => new stdClass(),
            'ratio' => NAN,
            'list' => [1, 'two', null, true],
            'nested' => self::nested(12),
        ];
        $entry = self::factory()->create(LogLevel::Critical, 'diagnostics.client', '', $context, new RefCode('7F3K-92QD'));
        $serializer = new Serializer();

        self::assertSame([], (new ContractValidator())->errorsInJson($serializer->json($serializer->toTransport($entry)), 'log-entry'));
    }

    private static function factory(): LogEntryFactory
    {
        return new LogEntryFactory(
            new FixedClock(),
            new UuidGenerator(static fn(int $length): string => str_repeat("\0", $length)),
            new Redactor(),
        );
    }

    /** @return array<string, mixed> */
    private static function nested(int $levels): array
    {
        $value = ['name' => 'bottom'];
        for ($i = 1; $i < $levels; $i++) {
            $value = ['next' => $value];
        }

        return $value;
    }
}
