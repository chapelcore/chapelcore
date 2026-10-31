<?php

/**
 * Notation.php — checks the text formats of DOMAIN-MODEL §2 (Key, SemVer, Uuid,
 * non-empty text).
 *
 * Why: several objects share these formats (HealthCheck.name, VersionInfo.version,
 * LogEntry.id and .channel, and later ClientErrorReport.appVersion). Checking each
 * format in one place means every object rejects exactly what the contract schemas
 * reject.
 *
 * How it connects: called from model constructors. The patterns are copied from
 * key.schema.json, sem-ver.schema.json and uuid.schema.json in libs/shared/contract.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final class Notation
{
    /** Dotted identifier such as `php.version` (key.schema.json). */
    public const string KEY = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/D';

    /** Semantic Versioning 2.0.0, the official semver.org pattern (sem-ver.schema.json). */
    public const string SEM_VER = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
        . '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][a-zA-Z0-9-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][a-zA-Z0-9-]*))*))?'
        . '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/D';

    /** RFC 9562 UUID, lowercase and hyphenated (uuid.schema.json). */
    public const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D';

    private function __construct() {}

    /** @throws InvalidArgumentException when $value isn't a Key */
    public static function key(string $value, string $field): string
    {
        return self::matching(self::KEY, $value, $field, 'a key such as "php.version"');
    }

    /** @throws InvalidArgumentException when $value isn't a semantic version */
    public static function semVer(string $value, string $field): string
    {
        return self::matching(self::SEM_VER, $value, $field, 'a semantic version such as "1.0.0"');
    }

    /** @throws InvalidArgumentException when $value isn't a lowercase, hyphenated UUID */
    public static function uuid(string $value, string $field): string
    {
        return self::matching(self::UUID, $value, $field, 'a lowercase UUID such as "0199e5a2-7c3b-7d4e-9f1a-2b3c4d5e6f70"');
    }

    /** @throws InvalidArgumentException when $value is empty (DOMAIN-MODEL rule 4) */
    public static function text(string $value, string $field): string
    {
        if ($value === '') {
            throw new InvalidArgumentException("{$field} must not be empty");
        }

        return $value;
    }

    /** @param non-empty-string $pattern */
    private static function matching(string $pattern, string $value, string $field, string $expected): string
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException("{$field} must be {$expected}, got \"{$value}\"");
        }

        return $value;
    }
}
