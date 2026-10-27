<?php

/**
 * PhpVersionProbeTest.php — tests the doctor's `php.version` check.
 *
 * Why: ChapelCore needs PHP 8.4 or newer, and the host's selector can quietly be on an
 * older version (DOMAIN-MODEL §5.8; hosting runbook §3). Below 8.4 the check is down,
 * with the exact cPanel setting that fixes it.
 *
 * How it connects: tests src/Doctor/PhpVersionProbe.php with given version strings; by
 * default the probe reads the PHP running the doctor.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\PhpVersionProbe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpVersionProbeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function supported(): iterable
    {
        yield 'the minimum' => ['8.4.0'];
        yield 'the target' => ['8.5.11'];
        yield 'a distribution build' => ['8.5.11-1ubuntu1'];
        yield 'a later major' => ['9.0.0'];
    }

    #[DataProvider('supported')]
    public function testASupportedVersionIsOk(string $version): void
    {
        $check = (new PhpVersionProbe($version))->check();

        self::assertSame(['php.version', Status::Ok, "PHP {$version}", null], [$check->name, $check->status, $check->message, $check->fix]);
    }

    public function testAnOlderVersionIsDownWithTheFix(): void
    {
        $check = (new PhpVersionProbe('8.3.6'))->check();

        self::assertSame(
            [Status::Down, 'PHP 8.3.6 is too old; ChapelCore needs 8.4 or newer', 'Set PHP to 8.5 in cPanel → PHP Tweaks → Account settings.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testReadsTheRunningPhpByDefault(): void
    {
        self::assertSame('PHP ' . PHP_VERSION, (new PhpVersionProbe())->check()->message);
    }
}
