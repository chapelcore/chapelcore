<?php

/**
 * PhpExtensionsProbe.php — the doctor's `php.extensions` check.
 *
 * Why: on cPanel each extension is a tick box, and a missing one breaks ChapelCore in
 * confusing ways later (DOMAIN-MODEL §5.8; hosting runbook §3). The check names every
 * missing extension and where to switch it on. A missing required extension (including
 * the configured database's PDO driver) is down; only OPcache missing is degraded,
 * because everything still works, just more slowly.
 *
 * How it connects: one of the doctor's probes; it reads the PHP that runs the doctor,
 * unless a test gives it a list of extension names as PHP reports them.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;

final readonly class PhpExtensionsProbe implements DoctorProbe
{
    /** DOMAIN-MODEL §5.8, in its order; the PDO driver and OPcache are added after these. */
    private const array REQUIRED = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'intl', 'mbstring', 'openssl', 'pdo',
        'session', 'sodium', 'tokenizer', 'xml', 'xmlwriter', 'zip',
    ];

    private const string OPCACHE = 'opcache';

    /** @var list<string> lowercase, with "Zend OPcache" as "opcache" */
    private array $loaded;

    /** @param list<string>|null $loaded extension names as PHP reports them; null reads the running PHP */
    public function __construct(
        private DatabaseDriver $driver,
        ?array $loaded = null,
    ) {
        $names = $loaded ?? [...get_loaded_extensions(), ...get_loaded_extensions(true)];
        $this->loaded = array_map(
            static fn(string $name): string => strtolower($name) === 'zend opcache' ? self::OPCACHE : strtolower($name),
            $names,
        );
    }

    public function name(): string
    {
        return 'php.extensions';
    }

    public function check(): DoctorCheck
    {
        $required = [...self::REQUIRED, $this->driver->pdoExtension()];
        $missing = array_values(array_diff([...$required, self::OPCACHE], $this->loaded));

        if ($missing === []) {
            return new DoctorCheck($this->name(), Status::Ok, 'All required extensions are loaded', null);
        }

        $fix = 'Enable ' . self::listInWords($missing) . ' in cPanel → PHP Tweaks → Extensions.';
        if ($missing === [self::OPCACHE]) {
            return new DoctorCheck($this->name(), Status::Degraded, 'Missing: opcache (pages will be slower)', $fix);
        }

        return new DoctorCheck($this->name(), Status::Down, 'Missing: ' . implode(', ', $missing), $fix);
    }

    /** @param non-empty-list<string> $names "a", "a and b", "a, b and c" */
    private static function listInWords(array $names): string
    {
        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names) . ' and ' . $last;
    }
}
