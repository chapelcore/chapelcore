<?php

/**
 * PhpArgon2ProbeTest.php — tests the doctor's `php.argon2` check.
 *
 * Why: passwords are hashed with Argon2id when PHP was built with it (CLAUDE.md,
 * Passwords). Without it they fall back to bcrypt, which is still safe, so the check
 * is degraded rather than down (DOMAIN-MODEL §5.8; plan risk R5).
 *
 * How it connects: tests src/Doctor/PhpArgon2Probe.php; by default the probe asks the
 * PHP running the doctor whether PASSWORD_ARGON2ID is defined.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\PhpArgon2Probe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\TestCase;

final class PhpArgon2ProbeTest extends TestCase
{
    public function testAvailableIsOk(): void
    {
        $check = (new PhpArgon2Probe(true))->check();

        self::assertSame(
            ['php.argon2', Status::Ok, 'Argon2id is available for passwords', null],
            [$check->name, $check->status, $check->message, $check->fix],
        );
    }

    public function testMissingIsDegradedWithTheFix(): void
    {
        $check = (new PhpArgon2Probe(false))->check();

        self::assertSame(
            [Status::Degraded, 'Argon2id is not available, so passwords fall back to bcrypt', 'Ask your host for PHP built with Argon2 (libargon2).'],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testReadsTheRunningPhpByDefault(): void
    {
        $expected = defined('PASSWORD_ARGON2ID') ? Status::Ok : Status::Degraded;

        self::assertSame($expected, (new PhpArgon2Probe())->check()->status);
    }
}
