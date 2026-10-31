<?php

/**
 * PhpExtensionsProbeTest.php — tests the doctor's `php.extensions` check.
 *
 * Why: on cPanel each extension is a tick box, and a missing one breaks ChapelCore in
 * confusing ways later (DOMAIN-MODEL §5.8; hosting runbook §3). The check names every
 * missing extension and says where to switch it on. The PDO driver depends on the
 * configured database. A missing required extension is down; only OPcache missing is
 * degraded, because everything still works, just more slowly.
 *
 * How it connects: tests src/Doctor/PhpExtensionsProbe.php with given extension lists
 * (as PHP reports them); by default the probe reads the PHP running the doctor.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Doctor\PhpExtensionsProbe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhpExtensionsProbeTest extends TestCase
{
    /** The required list from DOMAIN-MODEL §5.8, written as PHP reports it (case included). */
    private const array REQUIRED = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'intl', 'mbstring', 'openssl', 'PDO',
        'session', 'sodium', 'tokenizer', 'xml', 'xmlwriter', 'zip',
    ];

    public function testAllRequiredExtensionsWithOpcacheAreOk(): void
    {
        $check = (new PhpExtensionsProbe(DatabaseDriver::MariaDb, self::loaded('pdo_mysql', 'Zend OPcache')))->check();

        self::assertSame(
            ['php.extensions', Status::Ok, 'All required extensions are loaded', null],
            [$check->name, $check->status, $check->message, $check->fix],
        );
    }

    /** @return iterable<string, array{DatabaseDriver, string}> */
    public static function drivers(): iterable
    {
        yield 'MariaDB needs pdo_mysql' => [DatabaseDriver::MariaDb, 'pdo_mysql'];
        yield 'PostgreSQL needs pdo_pgsql' => [DatabaseDriver::PostgreSql, 'pdo_pgsql'];
    }

    #[DataProvider('drivers')]
    public function testThePdoDriverFollowsTheDatabase(DatabaseDriver $driver, string $pdoExtension): void
    {
        $with = (new PhpExtensionsProbe($driver, self::loaded($pdoExtension, 'Zend OPcache')))->check();
        $without = (new PhpExtensionsProbe($driver, self::loaded('Zend OPcache')))->check();

        self::assertSame(Status::Ok, $with->status);
        self::assertSame([Status::Down, "Missing: {$pdoExtension}"], [$without->status, $without->message]);
    }

    public function testOneMissingExtensionIsDownWithTheFix(): void
    {
        $loaded = array_values(array_diff(self::loaded('pdo_mysql', 'Zend OPcache'), ['intl']));

        $check = (new PhpExtensionsProbe(DatabaseDriver::MariaDb, $loaded))->check();

        self::assertSame(
            [Status::Down, 'Missing: intl', 'Enable intl in cPanel → PHP Tweaks → Extensions.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testEveryMissingExtensionIsNamedInTheRequiredOrder(): void
    {
        $loaded = array_values(array_diff(self::loaded('pdo_mysql'), ['zip', 'intl', 'mbstring']));

        $check = (new PhpExtensionsProbe(DatabaseDriver::MariaDb, $loaded))->check();

        self::assertSame(
            [Status::Down, 'Missing: intl, mbstring, zip, opcache', 'Enable intl, mbstring, zip and opcache in cPanel → PHP Tweaks → Extensions.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testOnlyOpcacheMissingIsDegraded(): void
    {
        $check = (new PhpExtensionsProbe(DatabaseDriver::MariaDb, self::loaded('pdo_mysql')))->check();

        self::assertSame(
            [Status::Degraded, 'Missing: opcache (pages will be slower)', 'Enable opcache in cPanel → PHP Tweaks → Extensions.'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testReadsTheRunningPhpByDefault(): void
    {
        $check = (new PhpExtensionsProbe(DatabaseDriver::MariaDb))->check();

        self::assertSame('php.extensions', $check->name);
        if (extension_loaded('intl')) {
            self::assertStringNotContainsString('intl', (string) $check->message);
        } else {
            self::assertStringContainsString('intl', (string) $check->message);
        }
    }

    /** @return list<string> */
    private static function loaded(string ...$extra): array
    {
        return array_values([...self::REQUIRED, 'Core', 'date', 'json', ...$extra]);
    }
}
