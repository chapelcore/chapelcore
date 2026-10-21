<?php

/**
 * SchemasTest.php — rules every schema file must follow, checked with opis.
 *
 * Why: the PHP twin of tests/ts/schemas.test.ts. It checks the same house rules
 * (draft 2020-12, `$id` matching the file name, a `title`, DOMAIN-MODEL rules 2 and 6)
 * and that opis can load every schema and resolve every `$ref`. Checking a schema
 * against the official meta-schema happens once, in the TypeScript test: opis does not
 * ship the meta-schema, and the tests never download anything.
 *
 * How it connects: reads schemas/ through Support.php; runs with `composer test`.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use Opis\JsonSchema\Schemas\ExceptionSchema;
use Opis\JsonSchema\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SchemasTest extends TestCase
{
    private const string DRAFT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    /** @return iterable<string, array{string, stdClass}> */
    public static function schemas(): iterable
    {
        foreach (Support::loadSchemas() as $name => $schema) {
            yield $name => [$name, $schema];
        }
    }

    public function testTheContractHasSchemas(): void
    {
        self::assertNotEmpty(Support::loadSchemas());
    }

    #[DataProvider('schemas')]
    public function testUsesDraft202012(string $name, stdClass $schema): void
    {
        self::assertSame(self::DRAFT_2020_12, $schema->{'$schema'} ?? null);
    }

    #[DataProvider('schemas')]
    public function testHasAnIdThatMatchesItsFileName(string $name, stdClass $schema): void
    {
        self::assertSame(Support::schemaId($name), $schema->{'$id'} ?? null);
    }

    #[DataProvider('schemas')]
    public function testHasATitleNamingTheDomainModelObject(string $name, stdClass $schema): void
    {
        self::assertMatchesRegularExpression('/^[A-Z][A-Za-z0-9]*$/', (string) ($schema->title ?? ''));
    }

    #[DataProvider('schemas')]
    public function testLoadsInOpisWithEveryRefResolved(string $name, stdClass $schema): void
    {
        $loader = Support::createValidator()->loader();
        $id = Support::schemaId($name);

        $problems = [];
        foreach (array_merge([$id], self::refs($schema)) as $ref) {
            $uri = Uri::merge($ref, $id);
            $loaded = $uri === null ? null : $loader->loadSchemaById($uri);
            if ($loaded === null) {
                $problems[] = "{$ref}: not found";
            } elseif ($loaded instanceof ExceptionSchema) {
                $problems[] = "{$ref}: opis cannot read it";
            }
        }

        self::assertSame([], $problems);
    }

    #[DataProvider('schemas')]
    public function testRequiresEveryFieldAndAllowsNoOthers(string $name, stdClass $schema): void
    {
        self::assertSame([], self::recordRuleViolations($schema));
    }

    /**
     * Every `$ref` in a schema.
     *
     * @return list<string>
     */
    private static function refs(mixed $node): array
    {
        if (!is_array($node) && !$node instanceof stdClass) {
            return [];
        }
        $refs = [];
        foreach ((array) $node as $key => $child) {
            if ($key === '$ref' && is_string($child)) {
                $refs[] = $child;
            } else {
                $refs = array_merge($refs, self::refs($child));
            }
        }

        return $refs;
    }

    /**
     * Same rules as recordRuleViolations() in schemas.test.ts:
     * - an object must set `additionalProperties`: `false` for a record with named
     *   fields, or a schema for a map such as `error.fields`;
     * - a record (an object with `properties`) must forbid extra keys and require
     *   every one of its fields, because nullable fields are present as `null`.
     *
     * @return list<string>
     */
    private static function recordRuleViolations(mixed $node, string $at = ''): array
    {
        if (is_array($node)) {
            $problems = [];
            foreach ($node as $i => $item) {
                $problems = array_merge($problems, self::recordRuleViolations($item, "{$at}/{$i}"));
            }

            return $problems;
        }
        if (!$node instanceof stdClass) {
            return [];
        }

        $problems = [];
        $where = $at === '' ? '/' : $at;
        $types = is_array($node->type ?? null) ? $node->type : [$node->type ?? null];
        if (in_array('object', $types, true)) {
            if (!property_exists($node, 'additionalProperties')) {
                $problems[] = "{$where}: object must set additionalProperties (false, or a schema for a map)";
            }
            if (($node->properties ?? null) instanceof stdClass) {
                $fields = array_keys(get_object_vars($node->properties));
                $required = is_array($node->required ?? null) ? $node->required : [];
                sort($fields);
                sort($required);
                if (($node->additionalProperties ?? null) !== false) {
                    $problems[] = "{$where}: a record with properties must set additionalProperties: false";
                }
                if ($fields !== $required) {
                    $problems[] = "{$where}: required must list every field exactly once (fields: "
                        . implode(', ', $fields) . '; required: ' . implode(', ', $required) . ')';
                }
            }
        }
        foreach (get_object_vars($node) as $key => $child) {
            if (in_array($key, ['enum', 'const', 'examples'], true)) {
                continue; // literal values, not schemas
            }
            $problems = array_merge($problems, self::recordRuleViolations($child, "{$at}/{$key}"));
        }

        return $problems;
    }
}
