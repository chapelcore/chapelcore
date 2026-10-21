<?php

/**
 * FixturesTest.php — runs every fixture through opis: valid ones must pass, invalid
 * ones must fail for the reason they state.
 *
 * Why: the PHP twin of tests/ts/fixtures.test.ts. Both languages read the same fixture
 * files, so a fixture that passes in one and fails in the other shows the two
 * validators disagree, which is exactly what the contract must never allow.
 *
 * How it connects: reads schemas/ and fixtures/ through Support.php; runs with
 * `composer test`.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function schemaNames(): iterable
    {
        foreach (array_keys(Support::loadSchemas()) as $name) {
            yield $name => [$name];
        }
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function validFixtures(): iterable
    {
        foreach (Support::loadValidFixtures() as $fixture) {
            yield "valid/{$fixture['schema']}/{$fixture['name']}" => [$fixture['schema'], $fixture['data']];
        }
    }

    /** @return iterable<string, array{string, array{path: string, keyword: string}, mixed}> */
    public static function invalidFixtures(): iterable
    {
        foreach (Support::loadInvalidFixtures() as $fixture) {
            yield "invalid/{$fixture['schema']}/{$fixture['name']}: {$fixture['description']}" => [
                $fixture['schema'],
                $fixture['expect'],
                $fixture['data'],
            ];
        }
    }

    public function testTheContractHasValidAndInvalidFixtures(): void
    {
        self::assertNotEmpty(Support::loadValidFixtures(), 'valid fixtures');
        self::assertNotEmpty(Support::loadInvalidFixtures(), 'invalid fixtures');
    }

    public function testEveryFixtureFolderBelongsToASchema(): void
    {
        $folders = array_unique(array_column(
            array_merge(Support::loadValidFixtures(), Support::loadInvalidFixtures()),
            'schema',
        ));

        self::assertSame([], array_values(array_diff($folders, array_keys(Support::loadSchemas()))));
    }

    #[DataProvider('schemaNames')]
    public function testSchemaHasAtLeastOneValidAndOneInvalidFixture(string $name): void
    {
        $hasValid = in_array($name, array_column(Support::loadValidFixtures(), 'schema'), true);
        $hasInvalid = in_array($name, array_column(Support::loadInvalidFixtures(), 'schema'), true);

        self::assertSame(
            ['hasValid' => true, 'hasInvalid' => true],
            ['hasValid' => $hasValid, 'hasInvalid' => $hasInvalid],
        );
    }

    #[DataProvider('validFixtures')]
    public function testValidFixturePasses(string $schema, mixed $data): void
    {
        $result = Support::createValidator()->validate($data, Support::schemaId($schema));

        self::assertSame([], Support::normalizeErrors($result->error()));
        self::assertTrue($result->isValid());
    }

    /** @param array{path: string, keyword: string} $expected */
    #[DataProvider('invalidFixtures')]
    public function testInvalidFixtureFailsForTheStatedReason(string $schema, array $expected, mixed $data): void
    {
        $result = Support::createValidator()->validate($data, Support::schemaId($schema));

        self::assertFalse($result->isValid());
        self::assertContains($expected, Support::normalizeErrors($result->error()));
    }
}
