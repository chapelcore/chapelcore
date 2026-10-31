<?php

/**
 * JsonLoggerTest.php — tests the PSR-3 logger that writes the server's JSON log.
 *
 * Why: CLAUDE.md, Logging: everything is logged through the logging port (PSR-3 in
 * PHP), and nothing is written before it is redacted. Each call adds one LogEntry as
 * one JSON line to a daily file, `chapelcore-YYYY-MM-DD.log` (UTC date). A log call
 * never throws: text that isn't valid UTF-8 is repaired rather than rejected, and if
 * the file can't be written, PHP's own error log gets a short note without the entry's
 * content.
 *
 * How it connects: tests src/Diagnostics/JsonLogger.php on real temporary folders.
 * Laravel (1.5) uses it as its log channel, so every Log:: call goes through here.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Diagnostics\JsonLogger;
use ChapelCore\Core\Diagnostics\LogEntryFactory;
use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Diagnostics\UuidGenerator;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class JsonLoggerTest extends TestCase
{
    private string $dir = '';

    private string|false $errorLog = false;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/chapelcore-log-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->errorLog = ini_get('error_log');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->errorLog);
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testIsAPsr3Logger(): void
    {
        self::assertInstanceOf(LoggerInterface::class, $this->logger());
    }

    public function testWritesOneRedactedSchemaValidLine(): void
    {
        $this->logger()->error('Reset failed for ann@example.org', [
            'password' => 'hunter2',
            'exception' => new RuntimeException('Bearer abc.def rejected'),
        ]);

        $lines = $this->lines('chapelcore-2026-10-25.log');
        self::assertCount(1, $lines);
        self::assertSame([], (new ContractValidator())->errorsInJson($lines[0], 'log-entry'));
        self::assertStringNotContainsString('hunter2', $lines[0]);
        self::assertStringNotContainsString('ann@example.org', $lines[0]);
        self::assertStringNotContainsString('abc.def', $lines[0]);
    }

    public function testAppendsALinePerCall(): void
    {
        $logger = $this->logger();
        $logger->info('First');
        $logger->info('Second');

        self::assertSame(['First', 'Second'], array_map(self::message(...), $this->lines('chapelcore-2026-10-25.log')));
    }

    public function testStartsANewFileEachUtcDay(): void
    {
        $this->logger(new FixedClock('2026-10-25T23:59:59.999Z'))->info('Late');
        $this->logger(new FixedClock('2026-10-25T18:00:00-06:00'))->info('Next day in UTC');

        self::assertSame(['Late'], array_map(self::message(...), $this->lines('chapelcore-2026-10-25.log')));
        self::assertSame(['Next day in UTC'], array_map(self::message(...), $this->lines('chapelcore-2026-10-26.log')));
    }

    /** @return iterable<string, array{string}> */
    public static function levels(): iterable
    {
        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
            yield $level => [$level];
        }
    }

    #[DataProvider('levels')]
    public function testEachLevelMethodWritesItsLevel(string $level): void
    {
        $this->logger()->{$level}('Message');
        $this->logger()->log($level, 'Message');

        self::assertSame([$level, $level], array_map(self::level(...), $this->lines('chapelcore-2026-10-25.log')));
    }

    public function testRejectsAnUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->logger()->log('fatal', 'Message');
    }

    public function testUsesTheAppChannelAndNoRequestIdByDefault(): void
    {
        $this->logger()->info('Message');

        $entry = self::decode($this->lines('chapelcore-2026-10-25.log')[0]);
        self::assertSame(['app', null], [$entry['channel'] ?? 'missing', $entry['requestId'] ?? null]);
    }

    public function testUsesItsChannelAndTheCurrentRequestId(): void
    {
        $requestId = null;
        $logger = new JsonLogger($this->dir, self::factory(new FixedClock()), 'diagnostics.client', static function () use (&$requestId): ?RefCode {
            return $requestId;
        });

        $logger->info('Before the request');
        $requestId = new RefCode('7F3K-92QD');
        $logger->info('During the request');

        $entries = array_map(self::decode(...), $this->lines('chapelcore-2026-10-25.log'));
        self::assertSame(
            [['diagnostics.client', null], ['diagnostics.client', '7F3K-92QD']],
            array_map(static fn(array $entry): array => [$entry['channel'] ?? 'missing', $entry['requestId'] ?? null], $entries),
        );
    }

    public function testInvalidUtf8IsReplacedRatherThanLost(): void
    {
        $this->logger()->warning("Upload name caf\xE9.pdf rejected");

        self::assertSame("Upload name caf\u{FFFD}.pdf rejected", self::message($this->lines('chapelcore-2026-10-25.log')[0]));
    }

    public function testAFileThatCannotBeWrittenIsReportedWithoutItsContent(): void
    {
        $errorLog = $this->dir . '/php-errors.txt';
        ini_set('error_log', $errorLog);
        $logger = new JsonLogger($this->dir . '/missing', self::factory(new FixedClock()));

        $logger->error('Reset failed for ann@example.org');

        $reported = (string) file_get_contents($errorLog);
        self::assertStringContainsString('ChapelCore could not write to the log file', $reported);
        self::assertStringNotContainsString('Reset failed', $reported);
    }

    private function logger(?FixedClock $clock = null): JsonLogger
    {
        return new JsonLogger($this->dir, self::factory($clock ?? new FixedClock()));
    }

    private static function factory(FixedClock $clock): LogEntryFactory
    {
        return new LogEntryFactory($clock, new UuidGenerator(), new Redactor());
    }

    /** @return list<string> the file's lines, each of which must end with a newline */
    private function lines(string $file): array
    {
        $content = (string) file_get_contents($this->dir . '/' . $file);
        self::assertStringEndsWith("\n", $content);

        return explode("\n", rtrim($content, "\n"));
    }

    /** @return array<string, mixed> */
    private static function decode(string $line): array
    {
        /** @var array<string, mixed> */
        return json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    }

    private static function message(string $line): mixed
    {
        return self::decode($line)['message'] ?? null;
    }

    private static function level(string $line): mixed
    {
        return self::decode($line)['level'] ?? null;
    }
}
