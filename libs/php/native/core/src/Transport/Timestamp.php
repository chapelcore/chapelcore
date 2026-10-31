<?php

/**
 * Timestamp.php — writes an instant the way transport JSON needs it.
 *
 * Why: DOMAIN-MODEL §2 (model 0.4): ChapelCore producers always write UTC with a
 * 3-digit millisecond fraction and a `Z` (`2026-10-21T15:04:05.123Z`), never an offset.
 * Microseconds are cut rather than rounded, so a time never moves into the next second.
 *
 * Reading is stricter than DateTimeImmutable: only the contract's shape (seconds, an
 * optional 3- or 6-digit fraction, `Z`) is accepted, and an impossible date such as
 * 30 February is rejected instead of rolling over into March.
 *
 * How it connects: the Serializer uses format() for every Timestamp field (LogEntry.occurredAt
 * first); the logger (checkpoint 4b) uses it for dates found in log context;
 * ClientErrorReportParser uses parse() for the browser's occurredAt.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Transport;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

final class Timestamp
{
    /** The pattern of timestamp.schema.json, with the seconds and the fraction captured. */
    public const string PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{3}|\d{6}))?Z$/D';

    private function __construct() {}

    public static function format(DateTimeInterface $instant): string
    {
        return DateTimeImmutable::createFromInterface($instant)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.v\Z');
    }

    /** @throws InvalidArgumentException when $value isn't a Timestamp or names an impossible time */
    public static function parse(string $value): DateTimeImmutable
    {
        if (preg_match(self::PATTERN, $value, $parts) === 1) {
            $micro = str_pad($parts[2] ?? '', 6, '0');
            $instant = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u', "{$parts[1]}.{$micro}", new DateTimeZone('UTC'));
            if ($instant !== false && $instant->format('Y-m-d\TH:i:s') === $parts[1]) {
                return $instant;
            }
        }

        throw new InvalidArgumentException("A timestamp must be UTC such as \"2026-10-21T15:04:05.123Z\", got \"{$value}\"");
    }
}
