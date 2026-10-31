<?php

/**
 * ContractValidator.php — validates JSON against the contract's schemas.
 *
 * Why: the self-tests prove the schemas say what DOMAIN-MODEL.md says; this class
 * lets code that *produces* JSON prove its output matches them. The PHP core's tests
 * use it from iteration 1.4, Laravel's contract suite from 1.8. Errors come back as
 * { path, keyword } pairs, the shape the fixtures use, so opis and ajv results can be
 * compared one to one.
 *
 * How it connects: reads schemas/ in this package. tests/php/Support.php builds its
 * validator and error lists through it; other packages require chapelcore/contract
 * as a local Composer path package.
 */

declare(strict_types=1);

namespace ChapelCore\Contract;

use InvalidArgumentException;
use JsonException;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Errors\ValidationError;
use stdClass;

final class ContractValidator
{
    /** Every schema's `$id` starts with this; the file name follows. Nothing is hosted there. */
    public const string SCHEMA_ID_BASE = 'https://chapelcore.org/contract/schemas/';

    private readonly CompliantValidator $validator;

    public function __construct()
    {
        $this->validator = self::createOpisValidator();
    }

    public static function schemasDir(): string
    {
        return dirname(__DIR__) . '/schemas';
    }

    /**
     * A standards-only opis validator (no opis extensions) that finds every schema by
     * its `$id`, reports all errors rather than the first, and checks formats such as
     * `date-time`: the same settings as the ajv self-tests.
     */
    public static function createOpisValidator(): CompliantValidator
    {
        $validator = new CompliantValidator(null, PHP_INT_MAX, false);
        $validator->resolver()?->registerPrefix(self::SCHEMA_ID_BASE, self::schemasDir() . '/');

        return $validator;
    }

    /**
     * The `$id` of a contract schema, by file name (`health-check`).
     *
     * @throws InvalidArgumentException when $schema doesn't name a schema in schemas/
     */
    public static function schemaId(string $schema): string
    {
        if (
            preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $schema) !== 1
            || !is_file(self::schemasDir() . "/{$schema}.schema.json")
        ) {
            throw new InvalidArgumentException("\"{$schema}\" is not a contract schema");
        }

        return self::SCHEMA_ID_BASE . $schema . '.schema.json';
    }

    /**
     * Errors in a JSON document exactly as it would be sent. Validating the text rather
     * than a PHP array keeps `{}` and `[]` apart, which PHP arrays can't.
     *
     * @return list<array{path: string, keyword: string}> empty when the JSON is valid
     * @throws JsonException when $json isn't valid JSON
     * @throws InvalidArgumentException when $schema doesn't name a contract schema
     */
    public function errorsInJson(string $json, string $schema): array
    {
        return $this->errorsInData(json_decode($json, false, 512, JSON_THROW_ON_ERROR), $schema);
    }

    /**
     * Errors in data already decoded with `json_decode($json, false)` (objects as stdClass).
     *
     * @return list<array{path: string, keyword: string}> empty when the data is valid
     * @throws InvalidArgumentException when $schema doesn't name a contract schema
     */
    public function errorsInData(mixed $data, string $schema): array
    {
        return self::normalizeErrors($this->validator->validate($data, self::schemaId($schema))->error());
    }

    /**
     * Turns an opis error tree into { path, keyword } pairs in the shape fixtures use,
     * matching normalizeErrors() in tests/ts/support.ts:
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
}
