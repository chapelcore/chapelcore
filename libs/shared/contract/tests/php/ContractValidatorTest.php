<?php

/**
 * ContractValidatorTest.php — tests the contract's runtime validator.
 *
 * Why: from iteration 1.4 the PHP core (and later Laravel's contract suite) checks
 * its own output against the schemas through ContractValidator. It must report
 * errors in the same { path, keyword } shape as the self-tests, and refuse
 * anything that isn't a known schema.
 *
 * How it connects: tests src/ContractValidator.php; runs with `composer test`.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use ChapelCore\Contract\ContractValidator;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\TestCase;

final class ContractValidatorTest extends TestCase
{
    private const string LOG_ENTRY = <<<'JSON'
        {"id":"0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b","occurredAt":"2026-10-22T15:04:05.123Z",
         "level":"info","channel":"app","message":"Started","requestId":null,
         "context":CONTEXT,"exception":null}
        JSON;

    public function testFindsTheSchemasFolder(): void
    {
        self::assertFileExists(ContractValidator::schemasDir() . '/health-check.schema.json');
    }

    public function testValidJsonHasNoErrors(): void
    {
        $errors = (new ContractValidator())->errorsInJson(
            '{"name":"database","status":"ok","message":null}',
            'health-check',
        );

        self::assertSame([], $errors);
    }

    public function testInvalidJsonListsEachErrorAsPathAndKeyword(): void
    {
        $errors = (new ContractValidator())->errorsInJson(
            '{"name":"database","status":"ok","message":"Database available"}',
            'health-check',
        );

        self::assertContains(['path' => '/message', 'keyword' => 'type'], $errors);
    }

    public function testAnEmptyObjectAndAnEmptyListStayDifferent(): void
    {
        $validator = new ContractValidator();

        self::assertSame([], $validator->errorsInJson(str_replace('CONTEXT', '{}', self::LOG_ENTRY), 'log-entry'));
        self::assertContains(
            ['path' => '/context', 'keyword' => 'type'],
            $validator->errorsInJson(str_replace('CONTEXT', '[]', self::LOG_ENTRY), 'log-entry'),
        );
    }

    public function testAcceptsAlreadyDecodedData(): void
    {
        $data = json_decode('{"version":"1.0.0","codename":"Kootenai"}', false, 512, JSON_THROW_ON_ERROR);

        self::assertSame([], (new ContractValidator())->errorsInData($data, 'version-info'));
    }

    public function testRejectsAnUnknownSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ContractValidator())->errorsInJson('{}', 'no-such-schema');
    }

    public function testRejectsASchemaNameThatLeavesTheSchemasFolder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ContractValidator())->errorsInJson('{}', '../composer');
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(JsonException::class);
        (new ContractValidator())->errorsInJson('{"name":', 'health-check');
    }
}
