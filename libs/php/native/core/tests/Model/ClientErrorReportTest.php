<?php

/**
 * ClientErrorReportTest.php — tests the limits of a browser error report.
 *
 * Why: the endpoint is public, so the report object itself refuses anything outside
 * DOMAIN-MODEL §5.8: a path with a query string or fragment (they can carry tokens),
 * empty texts, and texts or stacks over their limits. Lengths are characters, not
 * bytes, as in JSON Schema, so accented text isn't cut short.
 *
 * How it connects: tests src/Model/ClientErrorReport.php; the parser turns the same
 * rules into field messages before this constructor runs.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\ClientApp;
use ChapelCore\Core\Model\ClientErrorReport;
use ChapelCore\Core\Tests\Examples;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-type Changes array{appVersion?: string, path?: string, message?: string, stack?: list<string>, userAgent?: string}
 */
final class ClientErrorReportTest extends TestCase
{
    /** @return iterable<string, array{Changes}> */
    public static function withinTheLimits(): iterable
    {
        yield 'no stack' => [['stack' => []]];
        yield 'root path' => [['path' => '/']];
        yield 'path of 2048 characters' => [['path' => '/' . str_repeat('a', 2047)]];
        yield 'message of 1000 characters' => [['message' => str_repeat('m', 1000)]];
        yield 'message of 1000 accented characters (2000 bytes)' => [['message' => str_repeat('é', 1000)]];
        yield '50 stack lines of 500 characters' => [['stack' => array_fill(0, 50, str_repeat('s', 500))]];
        yield 'user agent of 512 characters' => [['userAgent' => str_repeat('u', 512)]];
        yield 'pre-release version' => [['appVersion' => '1.0.0-rc.1']];
    }

    /** @return iterable<string, array{Changes}> */
    public static function outsideTheLimits(): iterable
    {
        yield 'version not semver' => [['appVersion' => 'v1']];
        yield 'empty path' => [['path' => '']];
        yield 'relative path' => [['path' => 'admin/status']];
        yield 'query string' => [['path' => '/admin/status?token=abc']];
        yield 'fragment' => [['path' => '/admin/status#top']];
        yield 'path of 2049 characters' => [['path' => '/' . str_repeat('a', 2048)]];
        yield 'empty message' => [['message' => '']];
        yield 'message of 1001 characters' => [['message' => str_repeat('m', 1001)]];
        yield '51 stack lines' => [['stack' => array_fill(0, 51, 's')]];
        yield 'empty stack line' => [['stack' => ['ok', '']]];
        yield 'stack line of 501 characters' => [['stack' => ['ok', str_repeat('s', 501)]]];
        yield 'empty user agent' => [['userAgent' => '']];
        yield 'user agent of 513 characters' => [['userAgent' => str_repeat('u', 513)]];
    }

    public function testKeepsWhatWasSent(): void
    {
        $report = Examples::clientErrorReport();

        self::assertSame(
            [ClientApp::Admin, '0.1.0', '/admin/status', '    at StatusPage (status.vue:42:13)', '2026-10-21T15:04:05.123'],
            [$report->app, $report->appVersion, $report->path, $report->stack[1], $report->occurredAt->format('Y-m-d\TH:i:s.v')],
        );
    }

    /** @param Changes $changes */
    #[DataProvider('withinTheLimits')]
    public function testAcceptsValuesWithinTheLimits(array $changes): void
    {
        $report = self::report($changes);

        self::assertSame($changes, array_intersect_key(get_object_vars($report), $changes));
    }

    /** @param Changes $changes */
    #[DataProvider('outsideTheLimits')]
    public function testRejectsValuesOutsideTheLimits(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::report($changes);
    }

    /**
     * The example report with some fields changed.
     *
     * @param Changes $changes
     */
    private static function report(array $changes): ClientErrorReport
    {
        $example = Examples::clientErrorReport();

        return new ClientErrorReport(
            $example->app,
            $changes['appVersion'] ?? $example->appVersion,
            $changes['path'] ?? $example->path,
            $changes['message'] ?? $example->message,
            $changes['stack'] ?? $example->stack,
            $changes['userAgent'] ?? $example->userAgent,
            $example->occurredAt,
        );
    }
}
