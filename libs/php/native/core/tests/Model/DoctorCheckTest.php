<?php

/**
 * DoctorCheckTest.php — tests one line of the doctor's report (DOMAIN-MODEL §5.8).
 *
 * Why: the doctor runs from the command line (and from Phase 3 the signed-in admin),
 * never on a public endpoint, so unlike a HealthCheck its message may name versions,
 * paths and extensions, even when the check passes ("PHP 8.5.11"). A failing check
 * carries a plain-language fix; a passing one never does.
 *
 * How it connects: tests src/Model/DoctorCheck.php; the probes in src/Doctor create
 * these, and SerializerTest checks them against doctor-check.schema.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DoctorCheckTest extends TestCase
{
    public function testAPassingCheckMayDescribeWhatItFound(): void
    {
        $check = new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null);

        self::assertSame(['php.version', Status::Ok, 'PHP 8.5.11', null], [$check->name, $check->status, $check->message, $check->fix]);
    }

    public function testAFailingCheckCarriesItsFix(): void
    {
        $check = new DoctorCheck('php.extensions', Status::Down, 'Missing: intl', 'Enable intl in cPanel → PHP Tweaks → Extensions.');

        self::assertSame('Enable intl in cPanel → PHP Tweaks → Extensions.', $check->fix);
    }

    /** @return iterable<string, array{string, Status, ?string, ?string}> */
    public static function brokenRules(): iterable
    {
        yield 'name not a key' => ['PHP version', Status::Ok, null, null];
        yield 'empty message' => ['php.version', Status::Down, '', 'Set PHP to 8.5.'];
        yield 'empty fix' => ['php.version', Status::Down, 'PHP 8.3.6', ''];
        yield 'a fix on a passing check' => ['php.version', Status::Ok, 'PHP 8.5.11', 'Nothing to do.'];
    }

    #[DataProvider('brokenRules')]
    public function testRejectsABrokenRule(string $name, Status $status, ?string $message, ?string $fix): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DoctorCheck($name, $status, $message, $fix);
    }
}
