<?php

/**
 * VersionInfoTest.php — tests the release version and codename.
 *
 * Why: the version comes from version.json and is shown in the footer, the admin badge
 * and /health. A malformed version.json must fail loudly here, not reach a page.
 *
 * How it connects: tests src/Model/VersionInfo.php; VersionReader (checkpoint 3)
 * builds one from version.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\VersionInfo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VersionInfoTest extends TestCase
{
    public function testKeepsVersionAndCodename(): void
    {
        $info = new VersionInfo('1.0.0', 'Kootenai');

        self::assertSame(['1.0.0', 'Kootenai'], [$info->version, $info->codename]);
    }

    public function testTheCodenameIsOptional(): void
    {
        self::assertNull((new VersionInfo('0.1.0', null))->codename);
    }

    public function testRejectsAVersionThatIsNotSemVer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VersionInfo('1.0', null);
    }

    public function testRejectsAnEmptyCodename(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VersionInfo('1.0.0', '');
    }
}
