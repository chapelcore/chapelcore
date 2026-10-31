<?php

/**
 * StorageProbeTest.php — tests the `storage` health check.
 *
 * Why: logs, caches and uploads all need a writable storage folder. On shared hosting
 * it can also be full (disk quota), so the probe writes and removes a small file rather
 * than only asking whether the folder looks writable.
 *
 * How it connects: tests src/Health/StorageProbe.php on real temporary folders.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Health;

use ChapelCore\Core\Health\StorageProbe;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\TestCase;

final class StorageProbeTest extends TestCase
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

    public function testAWritableFolderIsOkAndLeftEmpty(): void
    {
        $check = (new StorageProbe($this->dir))->check();

        self::assertSame([Status::Ok, null], [$check->status, $check->message]);
        self::assertSame(['.', '..'], scandir($this->dir));
    }

    public function testAReadOnlyFolderIsDown(): void
    {
        chmod($this->dir, 0555);
        if (is_writable($this->dir)) {
            self::markTestSkipped('Running as a user who can write anywhere (root).');
        }

        $check = (new StorageProbe($this->dir))->check();

        self::assertSame([Status::Down, 'Storage not writable'], [$check->status, $check->message]);
    }

    public function testAMissingFolderIsDown(): void
    {
        $check = (new StorageProbe($this->dir . '/missing'))->check();

        self::assertSame([Status::Down, 'Storage not writable'], [$check->status, $check->message]);
    }

    public function testIsNamedStorage(): void
    {
        self::assertSame('storage', (new StorageProbe($this->dir))->name());
    }
}
