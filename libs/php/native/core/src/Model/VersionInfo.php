<?php

/**
 * VersionInfo.php — the release version and its codename (DOMAIN-MODEL §4).
 *
 * Why: version.json is the only place the version is written (CLAUDE.md "Versioning");
 * this object is what it becomes once read, and it rejects a malformed file.
 *
 * How it connects: HealthStatus carries it; VersionReader (checkpoint 3) builds it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class VersionInfo
{
    /** @throws InvalidArgumentException when the version isn't SemVer or the codename is empty */
    public function __construct(
        public string $version,
        public ?string $codename,
    ) {
        Notation::semVer($version, 'VersionInfo.version');
        if ($codename !== null) {
            Notation::text($codename, 'VersionInfo.codename');
        }
    }
}
