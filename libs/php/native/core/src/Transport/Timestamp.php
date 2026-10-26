<?php

/**
 * Timestamp.php — writes an instant the way transport JSON needs it.
 *
 * Why: DOMAIN-MODEL §2 (model 0.4): ChapelCore producers always write UTC with a
 * 3-digit millisecond fraction and a `Z` (`2026-10-21T15:04:05.123Z`), never an offset.
 * Microseconds are cut rather than rounded, so a time never moves into the next second.
 *
 * How it connects: the Serializer uses it for every Timestamp field (LogEntry.occurredAt
 * first); the logger (checkpoint 4b) uses it for dates found in log context.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Transport;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Timestamp
{
    private function __construct() {}

    public static function format(DateTimeInterface $instant): string
    {
        return DateTimeImmutable::createFromInterface($instant)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }
}
