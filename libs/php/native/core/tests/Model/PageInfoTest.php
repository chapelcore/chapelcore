<?php

/**
 * PageInfoTest.php — tests pagination details for list responses.
 *
 * Why: perPage is capped at 100 (DOMAIN-MODEL §4) so no request can ask for a whole
 * table at once; pages start at 1.
 *
 * How it connects: tests src/Model/PageInfo.php; ResponseMeta carries one on lists.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\PageInfo;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageInfoTest extends TestCase
{
    /** @return iterable<string, array{int, int, int}> */
    public static function outOfRange(): iterable
    {
        yield 'page 0' => [0, 20, 0];
        yield 'perPage 0' => [1, 0, 0];
        yield 'perPage 101' => [1, 101, 0];
        yield 'negative total' => [1, 20, -1];
    }

    public function testAcceptsTheEdges(): void
    {
        $smallest = new PageInfo(1, 1, 0);
        $largest = new PageInfo(1, 100, 0);

        self::assertSame([1, 1, 0, 100], [$smallest->page, $smallest->perPage, $smallest->total, $largest->perPage]);
    }

    #[DataProvider('outOfRange')]
    public function testRejectsAValueOutOfRange(int $page, int $perPage, int $total): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PageInfo($page, $perPage, $total);
    }
}
