<?php

/**
 * RedactionCasesTest.php — checks the shared redaction cases are well-formed.
 *
 * Why: the PHP twin of tests/ts/redaction.test.ts. DOMAIN-MODEL §5.8 makes these cases
 * the definitive examples of the redaction rules, and every core library runs them, so
 * a malformed case would silently test nothing.
 *
 * How it connects: reads redaction/ and schemas/ through Support.php; runs with
 * `composer test`. The PHP core (libs/php/native/core) runs the same cases through its
 * Redactor.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RedactionCasesTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function cases(): iterable
    {
        foreach (Support::loadRedactionCases() as $case) {
            yield "redaction/{$case['name']}" => [$case['data']];
        }
    }

    public function testThereAreTextAndContextCases(): void
    {
        $kinds = [];
        foreach (Support::loadRedactionCases() as $case) {
            $kinds[] = $case['data'] instanceof stdClass ? ($case['data']->kind ?? null) : null;
        }
        $kinds = array_values(array_unique($kinds));
        sort($kinds);

        self::assertSame(['context', 'text'], $kinds);
    }

    #[DataProvider('cases')]
    public function testCaseMatchesTheRedactionCaseSchema(mixed $data): void
    {
        $result = Support::createValidator()->validate($data, Support::schemaId('redaction-case'));

        self::assertSame([], Support::normalizeErrors($result->error()));
        self::assertTrue($result->isValid());
    }
}
