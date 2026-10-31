<?php

/**
 * ErrorCodeTest.php — tests the Phase 1 error codes and their HTTP statuses.
 *
 * Why: error codes never change meaning (DOMAIN-MODEL §4). This test lists the table
 * exactly, so adding, renaming or remapping a code is a visible, reviewed change.
 *
 * How it connects: tests src/Model/ErrorCode.php; ApiError carries one, and Laravel
 * (1.5) uses httpStatus() for the response status.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\ErrorCode;
use PHPUnit\Framework\TestCase;

final class ErrorCodeTest extends TestCase
{
    private const array TABLE = [
        'NOT_FOUND' => 404,
        'METHOD_NOT_ALLOWED' => 405,
        'VALIDATION_FAILED' => 422,
        'PAYLOAD_TOO_LARGE' => 413,
        'RATE_LIMITED' => 429,
        'INTERNAL_ERROR' => 500,
        'SERVICE_UNAVAILABLE' => 503,
    ];

    public function testHasExactlyThePhase1CodesWithTheirHttpStatuses(): void
    {
        $actual = [];
        foreach (ErrorCode::cases() as $code) {
            $actual[$code->value] = $code->httpStatus();
        }

        self::assertSame(self::TABLE, $actual);
    }
}
