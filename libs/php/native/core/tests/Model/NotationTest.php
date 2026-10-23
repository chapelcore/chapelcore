<?php

/**
 * NotationTest.php — tests the notation types' formats (DOMAIN-MODEL §2).
 *
 * Why: Key and SemVer appear in several objects (HealthCheck.name now, LogEntry.channel
 * and ClientErrorReport.appVersion later). One place checks each format, so every
 * object rejects the same values the contract schemas reject.
 *
 * How it connects: tests src/Model/Notation.php; the patterns are copied from
 * key.schema.json and sem-ver.schema.json, and these cases mirror their fixtures.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\Notation;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotationTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validKeys(): iterable
    {
        yield 'one word' => ['database'];
        yield 'dotted' => ['php.extensions'];
        yield 'underscore and digit' => ['diagnostics.client_2'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Database'];
        yield 'hyphen' => ['php-version'];
        yield 'starts with a digit' => ['2fa'];
        yield 'empty segment' => ['php..version'];
        yield 'trailing dot' => ['php.'];
    }

    /** @return iterable<string, array{string}> */
    public static function validSemVers(): iterable
    {
        yield 'release' => ['1.0.0'];
        yield 'zero major' => ['0.4.0'];
        yield 'pre-release' => ['1.1.0-rc.1'];
        yield 'build metadata' => ['1.0.0+20261023'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSemVers(): iterable
    {
        yield 'empty' => [''];
        yield 'two parts' => ['1.0'];
        yield 'leading zero' => ['01.0.0'];
        yield 'v prefix' => ['v1.0.0'];
        yield 'empty pre-release' => ['1.0.0-'];
    }

    #[DataProvider('validKeys')]
    public function testAcceptsAKey(string $value): void
    {
        self::assertSame($value, Notation::key($value, 'name'));
    }

    #[DataProvider('invalidKeys')]
    public function testRejectsAnInvalidKey(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name');
        Notation::key($value, 'name');
    }

    #[DataProvider('validSemVers')]
    public function testAcceptsASemVer(string $value): void
    {
        self::assertSame($value, Notation::semVer($value, 'version'));
    }

    #[DataProvider('invalidSemVers')]
    public function testRejectsAnInvalidSemVer(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('version');
        Notation::semVer($value, 'version');
    }

    public function testAcceptsTextThatIsNotEmpty(): void
    {
        self::assertSame('laravel', Notation::text('laravel', 'backend'));
    }

    public function testRejectsEmptyText(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('backend');
        Notation::text('', 'backend');
    }
}
