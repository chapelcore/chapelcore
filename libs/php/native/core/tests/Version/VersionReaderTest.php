<?php

/**
 * VersionReaderTest.php — tests reading version.json.
 *
 * Why: version.json is the only place the version is written (CLAUDE.md "Versioning");
 * /health, the footer and the admin badge all show what it says. A broken file must
 * stop with a message that names the file and the problem, never show a wrong version.
 *
 * How it connects: tests src/Version/VersionReader.php against temporary files and
 * against the repository's real version.json.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Version;

use ChapelCore\Core\Version\VersionReader;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VersionReaderTest extends TestCase
{
    private string $file = '';

    /** @return iterable<string, array{string}> */
    public static function badStructure(): iterable
    {
        yield 'not JSON' => ['{"version": '];
        yield 'not an object' => ['["0.1.0"]'];
        yield 'version missing' => ['{"codename": "Kootenai"}'];
        yield 'codename missing' => ['{"version": "0.1.0"}'];
        yield 'version not a string' => ['{"version": 1, "codename": null}'];
        yield 'codename not a string' => ['{"version": "0.1.0", "codename": 7}'];
    }

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/chapelcore-version-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testReadsVersionAndCodename(): void
    {
        file_put_contents($this->file, '{"version": "1.0.0", "codename": "Kootenai"}');

        $info = (new VersionReader($this->file))->read();

        self::assertSame(['1.0.0', 'Kootenai'], [$info->version, $info->codename]);
    }

    public function testACodenameMayBeNull(): void
    {
        file_put_contents($this->file, '{"version": "1.0.0", "codename": null}');

        self::assertNull((new VersionReader($this->file))->read()->codename);
    }

    public function testReadsTheRepositorysVersionFile(): void
    {
        $info = (new VersionReader(dirname(__DIR__, 6) . '/version.json'))->read();

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $info->version);
    }

    public function testAMissingFileNamesThePath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($this->file);
        (new VersionReader($this->file))->read();
    }

    #[DataProvider('badStructure')]
    public function testABrokenFileNamesThePath(string $contents): void
    {
        file_put_contents($this->file, $contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($this->file);
        (new VersionReader($this->file))->read();
    }

    public function testAVersionThatIsNotSemVerIsRejected(): void
    {
        file_put_contents($this->file, '{"version": "1.0", "codename": null}');

        $this->expectException(InvalidArgumentException::class);
        (new VersionReader($this->file))->read();
    }
}
