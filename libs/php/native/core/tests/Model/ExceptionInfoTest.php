<?php

/**
 * ExceptionInfoTest.php — tests how an exception is recorded for the log.
 *
 * Why: DOMAIN-MODEL §5.8 (model 0.4). The message is redacted at every level of the
 * `previous` chain and is never empty (`(no message)`). Trace frames name the file,
 * line and function only, in PHP's own `#0 file(line): function()` layout, but never
 * argument values: arguments can hold passwords or personal data, and PHP keeps some
 * (such as an include's path) whatever the server's `zend.exception_ignore_args` says.
 *
 * How it connects: tests src/Model/ExceptionInfo.php with src/Diagnostics/Redactor.php;
 * LogEntry carries the result, and the Serializer writes it (SerializerTest).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Model\ExceptionInfo;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExceptionInfoTest extends TestCase
{
    private string|false $ignoreArgs = false;

    protected function setUp(): void
    {
        $this->ignoreArgs = ini_get('zend.exception_ignore_args');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', (string) $this->ignoreArgs);
    }

    public function testKeepsTheTypeFileAndLine(): void
    {
        [$exception, $line] = [new RuntimeException('Could not write the heartbeat file.'), __LINE__];

        $info = ExceptionInfo::fromThrowable($exception, new Redactor());

        self::assertSame(
            [RuntimeException::class, 'Could not write the heartbeat file.', __FILE__, $line, null],
            [$info->type, $info->message, $info->file, $info->line, $info->previous],
        );
    }

    public function testRedactsTheMessage(): void
    {
        $info = ExceptionInfo::fromThrowable(new RuntimeException('Login failed for ann@example.org'), new Redactor());

        self::assertSame('Login failed for [email]', $info->message);
    }

    public function testAnEmptyMessageIsWrittenAsNoMessage(): void
    {
        self::assertSame('(no message)', ExceptionInfo::fromThrowable(new RuntimeException(), new Redactor())->message);
    }

    public function testTraceFramesNameTheFileLineAndFunctionOnly(): void
    {
        [$exception, $line] = [self::failWith('hunter2'), __LINE__];

        $info = ExceptionInfo::fromThrowable($exception, new Redactor());

        self::assertSame('#0 ' . __FILE__ . "({$line}): " . self::class . '::failWith()', $info->trace[0]);
        self::assertCount(count($exception->getTrace()) + 1, $info->trace);
        foreach (array_slice($info->trace, 0, -1) as $number => $frame) {
            self::assertMatchesRegularExpression('/^#' . $number . ' (\S.*\(\d+\)|\[internal function\]): [^()]+\(\)$/D', $frame);
        }
        self::assertSame('#' . (count($info->trace) - 1) . ' {main}', $info->trace[count($info->trace) - 1]);
    }

    public function testTheTraceNeverCarriesArgumentValues(): void
    {
        ini_set('zend.exception_ignore_args', '0');
        $exception = self::failWith('hunter2');
        self::assertSame(['hunter2'], $exception->getTrace()[0]['args'] ?? null, 'PHP itself recorded the argument');

        $info = ExceptionInfo::fromThrowable($exception, new Redactor());

        self::assertStringEndsWith(self::class . '::failWith()', $info->trace[0]);
        self::assertStringNotContainsString('hunter2', implode("\n", $info->trace));
    }

    public function testFollowsThePreviousChainAndRedactsEveryMessage(): void
    {
        $cause = new RuntimeException('Mail to ann@example.org bounced');
        $exception = new LogicException('Reset for 406-293-1234 failed', 0, $cause);

        $info = ExceptionInfo::fromThrowable($exception, new Redactor());

        self::assertSame(['Reset for [number] failed', RuntimeException::class, 'Mail to [email] bounced', null], [
            $info->message,
            $info->previous?->type,
            $info->previous?->message,
            $info->previous?->previous,
        ]);
    }

    public function testMayHaveNoFileLineOrTrace(): void
    {
        $info = new ExceptionInfo('ErrorException', 'Permission denied', null, null, [], null);

        self::assertSame([null, null, []], [$info->file, $info->line, $info->trace]);
    }

    /** @return iterable<string, array{string, string, ?string, ?int, list<string>}> */
    public static function brokenRules(): iterable
    {
        yield 'empty type' => ['', 'Failed', '/app/a.php', 1, []];
        yield 'empty message' => ['RuntimeException', '', '/app/a.php', 1, []];
        yield 'empty file' => ['RuntimeException', 'Failed', '', 1, []];
        yield 'line zero' => ['RuntimeException', 'Failed', '/app/a.php', 0, []];
        yield 'empty trace line' => ['RuntimeException', 'Failed', '/app/a.php', 1, ['#0 {main}', '']];
    }

    /** @param list<string> $trace */
    #[DataProvider('brokenRules')]
    public function testRejectsABrokenRule(string $type, string $message, ?string $file, ?int $line, array $trace): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExceptionInfo($type, $message, $file, $line, $trace, null);
    }

    /** Creates an exception one call deep, so the first trace frame is this method with its argument. */
    private static function failWith(string $secret): RuntimeException
    {
        return new RuntimeException('Failed for ' . strlen($secret) . ' characters');
    }
}
