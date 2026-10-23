<?php

/**
 * Support.php — shared helpers for the contract's PHP self-tests.
 *
 * Why: every test needs the same three things: the schemas, the fixtures, and a
 * validator that knows all the schemas. Loading them in one place keeps the tests short
 * and makes sure every test reads the files the same way.
 *
 * How it connects: used by tests/php/*Test.php. It is the PHP twin of
 * tests/ts/support.ts and follows the same rules (folder layout, fixture wrapper, error
 * shape), so opis and ajv judge the same files identically. The validator settings and
 * the error shape come from src/ContractValidator.php, so tests and runtime agree.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use ChapelCore\Contract\ContractValidator;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;
use stdClass;

final class Support
{
    /** Every schema's `$id` starts with this; the file name follows. Nothing is hosted there. */
    public const string SCHEMA_ID_BASE = ContractValidator::SCHEMA_ID_BASE;

    /** The contract package folder (libs/shared/contract). */
    public static function contractRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function schemasDir(): string
    {
        return self::contractRoot() . '/schemas';
    }

    public static function fixturesDir(): string
    {
        return self::contractRoot() . '/fixtures';
    }

    public static function redactionDir(): string
    {
        return self::contractRoot() . '/redaction';
    }

    public static function schemaId(string $name): string
    {
        return self::SCHEMA_ID_BASE . $name . '.schema.json';
    }

    /**
     * Reads a JSON file. Objects become `stdClass`, as opis expects, so `{}` and `[]`
     * stay different, just as they are in JavaScript.
     */
    public static function readJson(string $path): mixed
    {
        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Schema files as name (file name without `.schema.json`) => parsed schema.
     *
     * @return array<string, stdClass>
     */
    public static function loadSchemas(): array
    {
        $schemas = [];
        foreach (self::listDir(self::schemasDir()) as $file) {
            if (str_ends_with($file, '.schema.json')) {
                $schema = self::readJson(self::schemasDir() . '/' . $file);
                if (!$schema instanceof stdClass) {
                    throw new RuntimeException("schemas/{$file}: a schema must be a JSON object");
                }
                $schemas[substr($file, 0, -strlen('.schema.json'))] = $schema;
            }
        }

        return $schemas;
    }

    /**
     * fixtures/valid/<schema>/<case>.json: plain data that must pass.
     *
     * @return list<array{schema: string, name: string, data: mixed}>
     */
    public static function loadValidFixtures(): array
    {
        return array_map(
            static fn(array $file): array => ['schema' => $file[0], 'name' => $file[1], 'data' => $file[2]],
            self::loadFixtureFiles('valid'),
        );
    }

    /**
     * fixtures/invalid/<schema>/<case>.json, wrapped as { description, expect: { path,
     * keyword }, data }. A badly written wrapper stops the run with a clear message, so
     * a typo in a fixture can never pass as "the data was rejected".
     *
     * @return list<array{schema: string, name: string, description: string, expect: array{path: string, keyword: string}, data: mixed}>
     */
    public static function loadInvalidFixtures(): array
    {
        return array_map(static function (array $file): array {
            [$schema, $name, $wrapper] = $file;
            $where = "fixtures/invalid/{$schema}/{$name}.json";
            if (!$wrapper instanceof stdClass || !property_exists($wrapper, 'data')) {
                throw new RuntimeException("{$where}: must be an object with description, expect and data");
            }
            if (!is_string($wrapper->description ?? null) || $wrapper->description === '') {
                throw new RuntimeException("{$where}: \"description\" must say why the data is invalid");
            }
            $expect = $wrapper->expect ?? null;
            if (!$expect instanceof stdClass || !is_string($expect->path ?? null) || !is_string($expect->keyword ?? null)) {
                throw new RuntimeException("{$where}: \"expect\" must be { \"path\": \"<JSON Pointer>\", \"keyword\": \"<rule>\" }");
            }

            return [
                'schema' => $schema,
                'name' => $name,
                'description' => $wrapper->description,
                'expect' => ['path' => $expect->path, 'keyword' => $expect->keyword],
                'data' => $wrapper->data,
            ];
        }, self::loadFixtureFiles('invalid'));
    }

    /**
     * redaction/<case>.json: the shared redaction examples (DOMAIN-MODEL §5.8).
     *
     * @return list<array{name: string, data: mixed}>
     */
    public static function loadRedactionCases(): array
    {
        $cases = [];
        foreach (self::listDir(self::redactionDir()) as $file) {
            if (str_ends_with($file, '.json')) {
                $cases[] = [
                    'name' => substr($file, 0, -strlen('.json')),
                    'data' => self::readJson(self::redactionDir() . '/' . $file),
                ];
            }
        }

        return $cases;
    }

    /** The contract's own opis settings (ContractValidator), so tests and runtime agree. */
    public static function createValidator(): CompliantValidator
    {
        return ContractValidator::createOpisValidator();
    }

    /**
     * { path, keyword } pairs in the shape fixtures use; see ContractValidator::normalizeErrors().
     *
     * @return list<array{path: string, keyword: string}>
     */
    public static function normalizeErrors(?ValidationError $error): array
    {
        return ContractValidator::normalizeErrors($error);
    }

    /** @return list<string> entry names in a folder, sorted so test order never depends on the disk */
    private static function listDir(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $entries = array_values(array_diff((array) scandir($dir), ['.', '..']));
        sort($entries);

        return $entries;
    }

    /** @return list<array{0: string, 1: string, 2: mixed}> [schema, case, parsed JSON] */
    private static function loadFixtureFiles(string $kind): array
    {
        $root = self::fixturesDir() . '/' . $kind;
        $files = [];
        foreach (self::listDir($root) as $schema) {
            foreach (self::listDir("{$root}/{$schema}") as $file) {
                if (str_ends_with($file, '.json')) {
                    $files[] = [$schema, substr($file, 0, -strlen('.json')), self::readJson("{$root}/{$schema}/{$file}")];
                }
            }
        }

        return $files;
    }
}
