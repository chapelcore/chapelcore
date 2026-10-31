<?php

/**
 * RefCodeTest.php — tests the reference code value object.
 *
 * Why: the reference code is what a person reads out to get help, and it must be the
 * same text in JSON, the X-Request-Id header and the log (DOMAIN-MODEL §2, RefCode).
 * A malformed one can't be allowed to exist.
 *
 * How it connects: tests src/Model/RefCode.php. Generating codes is checkpoint 3.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\RefCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RefCodeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidCodes(): iterable
    {
        yield 'lowercase' => ['7f3k-92qd'];
        yield 'no hyphen' => ['7F3K92QD'];
        yield 'too short' => ['7F3K-92Q'];
        yield 'letter I' => ['7F3I-92QD'];
        yield 'letter L' => ['7F3L-92QD'];
        yield 'letter O' => ['7F3O-92QD'];
        yield 'letter U' => ['7F3U-92QD'];
        yield 'surrounding space' => [' 7F3K-92QD'];
    }

    public function testKeepsAValidCode(): void
    {
        self::assertSame('7F3K-92QD', (new RefCode('7F3K-92QD'))->value);
    }

    #[DataProvider('invalidCodes')]
    public function testRejectsAnInvalidCode(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RefCode($value);
    }
}
