<?php

/**
 * StorageWritableProbeTest.php — tests the doctor's `storage.writable` check.
 *
 * Why: the same rule as the public `storage` health check (DOMAIN-MODEL §5.8), but the
 * doctor isn't public, so it names the folder and the permission that fixes it.
 *
 * How it connects: tests src/Doctor/StorageWritableProbe.php, which reuses the health
 * check's StorageProbe, on real temporary folders.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\StorageWritableProbe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\TestCase;

final class StorageWritableProbeTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/chapelcore-storage-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        chmod($this->dir, 0755);
        rmdir($this->dir);
    }

    public function testAWritableFolderIsOkAndNamed(): void
    {
        $check = (new StorageWritableProbe($this->dir))->check();

        self::assertSame(
            ['storage.writable', Status::Ok, "{$this->dir} is writable", null],
            [$check->name, $check->status, $check->message, $check->fix],
        );
    }

    public function testAReadOnlyFolderIsDownWithThePermissionToSet(): void
    {
        chmod($this->dir, 0555);
        if (is_writable($this->dir)) {
            self::markTestSkipped('Running as a user who can write anywhere (root).');
        }

        $check = (new StorageWritableProbe($this->dir))->check();

        self::assertSame(
            [
                Status::Down,
                "{$this->dir} is missing or not writable",
                "Make sure {$this->dir} exists and has permissions 755, so the account's user can write to it (cPanel → File Manager, or chmod 755 over SSH).",
            ],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testAMissingFolderIsDown(): void
    {
        $check = (new StorageWritableProbe($this->dir . '/missing'))->check();

        self::assertSame([Status::Down, "{$this->dir}/missing is missing or not writable"], [$check->status, $check->message]);
    }
}
