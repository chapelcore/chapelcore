<?php

/**
 * RedactorTest.php — runs the shared redaction cases against the PHP Redactor.
 *
 * Why: passwords, tokens, prayer text and personal data must never reach a log
 * (CLAUDE.md, Logging). The rules are in DOMAIN-MODEL §5.8 and the definitive examples
 * are the JSON files in libs/shared/contract/redaction, which the TypeScript core will
 * run too (1.6). Every case must give exactly its expected output, and redacting a
 * second time must change nothing.
 *
 * How it connects: tests src/Diagnostics/Redactor.php; finds the cases next to the
 * contract's schemas folder, so a new case file is picked up without a code change.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Diagnostics\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    /** @return iterable<string, array{string, mixed, mixed}> */
    public static function sharedCases(): iterable
    {
        $files = glob(dirname(ContractValidator::schemasDir()) . '/redaction/*.json');
        foreach ($files === false ? [] : $files as $file) {
            /** @var array{kind: string, input: mixed, expected: mixed} $case */
            $case = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            yield basename($file, '.json') => [$case['kind'], $case['input'], $case['expected']];
        }
    }

    #[DataProvider('sharedCases')]
    public function testGivesTheExpectedOutput(string $kind, mixed $input, mixed $expected): void
    {
        self::assertSame($expected, self::redact(new Redactor(), $kind, $input));
    }

    #[DataProvider('sharedCases')]
    public function testRedactingTwiceChangesNothing(string $kind, mixed $input, mixed $expected): void
    {
        $redactor = new Redactor();
        $once = self::redact($redactor, $kind, $input);

        self::assertSame($once, self::redact($redactor, $kind, $once));
    }

    private static function redact(Redactor $redactor, string $kind, mixed $value): mixed
    {
        if ($kind === 'text') {
            self::assertIsString($value);

            return $redactor->text($value);
        }
        self::assertIsArray($value);

        return $redactor->context($value);
    }
}
