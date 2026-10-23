<?php

/**
 * ApiErrorTest.php — tests the error part of an error envelope.
 *
 * Why: the message is shown to people, so it is never empty; field messages explain
 * what to fix, so a field never has an empty list or an empty message (DOMAIN-MODEL §4).
 *
 * How it connects: tests src/Model/ApiError.php; the Serializer wraps it with meta.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\ErrorCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiErrorTest extends TestCase
{
    /** @return iterable<string, array{array<string, list<string>>}> */
    public static function badFields(): iterable
    {
        yield 'empty field name' => [['' => ['Required']]];
        yield 'no messages for a field' => [['message' => []]];
        yield 'empty message' => [['message' => ['']]];
    }

    public function testHasNoFieldsByDefault(): void
    {
        $error = new ApiError(ErrorCode::NotFound, 'Not found');

        self::assertSame([ErrorCode::NotFound, 'Not found', []], [$error->code, $error->message, $error->fields]);
    }

    public function testKeepsFieldMessagesInOrder(): void
    {
        $fields = ['message' => ['Required', 'At most 1000 characters'], 'path' => ['Must start with /']];

        self::assertSame($fields, (new ApiError(ErrorCode::ValidationFailed, 'Check the highlighted fields', $fields))->fields);
    }

    public function testRejectsAnEmptyMessage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ApiError(ErrorCode::InternalError, '');
    }

    /** @param array<string, list<string>> $fields */
    #[DataProvider('badFields')]
    public function testRejectsBadFieldMessages(array $fields): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ApiError(ErrorCode::ValidationFailed, 'Check the highlighted fields', $fields);
    }
}
