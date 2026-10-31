<?php

/**
 * PageInfo.php — pagination details for a list response (DOMAIN-MODEL §4).
 *
 * Why: pages start at 1 and perPage is capped at 100, so no request can ask for a
 * whole table at once.
 *
 * How it connects: ResponseMeta carries it on paginated lists (from Phase 3).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class PageInfo
{
    public const int MAX_PER_PAGE = 100;

    /** @throws InvalidArgumentException when a value is out of range */
    public function __construct(
        public int $page,
        public int $perPage,
        public int $total,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException("PageInfo.page must be at least 1, got {$page}");
        }
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('PageInfo.perPage must be 1–' . self::MAX_PER_PAGE . ", got {$perPage}");
        }
        if ($total < 0) {
            throw new InvalidArgumentException("PageInfo.total must not be negative, got {$total}");
        }
    }
}
