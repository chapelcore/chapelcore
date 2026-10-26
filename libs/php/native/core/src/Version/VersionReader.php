<?php

/**
 * VersionReader.php — reads version.json into a VersionInfo.
 *
 * Why: version.json is the only place the version is written (CLAUDE.md "Versioning").
 * A broken file stops with a message naming the file and the problem, so a wrong
 * version is never shown.
 *
 * How it connects: Laravel (1.5) reads the deployed version.json and passes the result
 * to HealthService.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Version;

use ChapelCore\Core\Model\VersionInfo;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final readonly class VersionReader
{
    public function __construct(private string $path) {}

    /**
     * @throws RuntimeException when the file is missing, unreadable or not `{ "version", "codename" }`
     * @throws InvalidArgumentException when a value breaks VersionInfo's rules
     */
    public function read(): VersionInfo
    {
        $text = is_file($this->path) && is_readable($this->path) ? file_get_contents($this->path) : false;
        if ($text === false) {
            throw $this->broken('cannot be read');
        }

        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $this->broken('is not valid JSON', $e);
        }
        if (!is_array($data) || !array_key_exists('version', $data) || !array_key_exists('codename', $data)) {
            throw $this->broken('must be an object with "version" and "codename"');
        }

        $version = $data['version'];
        $codename = $data['codename'];
        if (!is_string($version)) {
            throw $this->broken('"version" must be a string');
        }
        if ($codename !== null && !is_string($codename)) {
            throw $this->broken('"codename" must be a string or null');
        }

        return new VersionInfo($version, $codename);
    }

    private function broken(string $problem, ?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException("{$this->path} {$problem}", 0, $previous);
    }
}
