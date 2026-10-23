<?php

/**
 * ServedByTest.php — tests the "which backend answered" block of every response.
 *
 * Why: ChapelCore is meant to run on more than one backend, so every response says
 * which one served it. The values come from runtime introspection, never literals
 * (DOMAIN-MODEL rule 12); none of them may be empty.
 *
 * How it connects: tests src/Model/ServedBy.php; Laravel (1.5) fills it in.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\ServedBy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServedByTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string}> */
    public static function oneFieldEmpty(): iterable
    {
        yield 'backend' => ['', '13.35.0', 'php', '8.5.11'];
        yield 'backendVersion' => ['laravel', '', 'php', '8.5.11'];
        yield 'language' => ['laravel', '13.35.0', '', '8.5.11'];
        yield 'languageVersion' => ['laravel', '13.35.0', 'php', ''];
    }

    public function testKeepsAllFour(): void
    {
        $servedBy = new ServedBy('laravel', '13.35.0', 'php', '8.5.11');

        self::assertSame(
            ['laravel', '13.35.0', 'php', '8.5.11'],
            [$servedBy->backend, $servedBy->backendVersion, $servedBy->language, $servedBy->languageVersion],
        );
    }

    #[DataProvider('oneFieldEmpty')]
    public function testRejectsAnEmptyField(string $backend, string $backendVersion, string $language, string $languageVersion): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ServedBy($backend, $backendVersion, $language, $languageVersion);
    }
}
