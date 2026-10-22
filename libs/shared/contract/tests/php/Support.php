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
 * shape), so opis and ajv judge the same files identically.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;
use stdClass;

final class Support
{
    /** Every schema's `$id` starts with this; the file name follows. Nothing is hosted there. */
    public const string SCHEMA_ID_BASE = 'https://chapelcore.org/contract/schemas/';

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
     * A standards-only validator (no opis extensions) that finds every schema by its
     * `$id`, reports all errors rather than the first, and checks formats such as
     * `date-time`, matching the ajv settings in support.ts.
     */
    public static function createValidator(): CompliantValidator
    {
        $validator = new CompliantValidator(null, PHP_INT_MAX, false);
        $validator->resolver()?->registerPrefix(self::SCHEMA_ID_BASE, self::schemasDir() . '/');

        return $validator;
    }

    /**
     * Turns an opis error tree into { path, keyword } pairs in the shape fixtures use,
     * matching normalizeErrors() in support.ts:
     * - only the innermost errors count (opis also reports the `properties` or `$ref`
     *   keyword that contained them; ajv does not);
     * - a missing field (`required`) or an unexpected one (`additionalProperties`)
     *   points at the field itself, one entry per field. opis also lists declared
     *   fields that failed their own rules as "additional"; those are skipped.
     *
     * @return list<array{path: string, keyword: string}>
     */
    public static function normalizeErrors(?ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }
        if ($error->subErrors() !== []) {
            return array_merge(...array_map(self::normalizeErrors(...), $error->subErrors()));
        }

        $path = self::jsonPointer($error->data()->fullPath());
        $args = $error->args();

        return match ($error->keyword()) {
            'required' => array_map(
                static fn(string $field): array => ['path' => "{$path}/{$field}", 'keyword' => 'required'],
                array_values($args['missing'] ?? []),
            ),
            'additionalProperties' => array_map(
                static fn(string $field): array => ['path' => "{$path}/{$field}", 'keyword' => 'additionalProperties'],
                array_values(array_diff($args['properties'] ?? [], self::declaredFields($error))),
            ),
            default => [['path' => $path, 'keyword' => $error->keyword()]],
        };
    }

    /** @param list<string|int> $segments */
    private static function jsonPointer(array $segments): string
    {
        return implode('', array_map(
            static fn(string|int $segment): string => '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $segment),
            $segments,
        ));
    }

    /** @return list<string> the field names the failing object's schema declares in `properties` */
    private static function declaredFields(ValidationError $error): array
    {
        $schema = $error->schema()->info()->data();
        $properties = $schema instanceof stdClass ? ($schema->properties ?? null) : null;

        return $properties instanceof stdClass ? array_keys(get_object_vars($properties)) : [];
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
