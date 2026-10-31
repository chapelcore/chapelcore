<?php

/**
 * RequestIdGeneratorTest.php — tests how new reference codes are made.
 *
 * Why: every request gets a reference code from 5 random bytes (40 bits) written as 8
 * Crockford Base32 characters, `XXXX-XXXX` (DOMAIN-MODEL §2). Known byte values must
 * give known codes, and the randomness must come from a secure source.
 *
 * How it connects: tests src/Diagnostics/RequestIdGenerator.php; Laravel (1.5) calls it
 * once per request and puts the code in meta.requestId, X-Request-Id and the log.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Core\Diagnostics\RequestIdGenerator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestIdGeneratorTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function knownBytes(): iterable
    {
        yield 'all zero bits' => ['0000000000', '0000-0000'];
        yield 'all one bits' => ['ffffffffff', 'ZZZZ-ZZZZ'];
        yield 'mixed' => ['0123456789', '04HM-ASW9'];
    }

    #[DataProvider('knownBytes')]
    public function testEncodesKnownBytes(string $hex, string $expected): void
    {
        $generator = new RequestIdGenerator(static fn(int $length): string => (string) hex2bin($hex));

        self::assertSame($expected, $generator->generate()->value);
    }

    public function testAsksForExactlyFiveBytes(): void
    {
        $asked = [];
        $generator = new RequestIdGenerator(static function (int $length) use (&$asked): string {
            $asked[] = $length;

            return str_repeat("\0", $length);
        });

        $generator->generate();

        self::assertSame([5], $asked);
    }

    public function testRejectsASourceThatGivesTheWrongNumberOfBytes(): void
    {
        $generator = new RequestIdGenerator(static fn(int $length): string => 'abcd');

        $this->expectException(LogicException::class);
        $generator->generate();
    }

    public function testRealCodesAreValidAndDiffer(): void
    {
        $generator = new RequestIdGenerator();
        $codes = [];
        for ($i = 0; $i < 1000; $i++) {
            $codes[] = $generator->generate()->value;
        }

        self::assertCount(1000, array_unique($codes));
    }
}
