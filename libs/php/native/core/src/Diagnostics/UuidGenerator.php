<?php

/**
 * UuidGenerator.php — makes the id of each log entry.
 *
 * Why: LogEntry.id is a Uuid (DOMAIN-MODEL §5.8). Version 7 (RFC 9562) puts the time in
 * milliseconds first, so ids sort in the order entries were written, which keeps a
 * database index compact once logs move into the database (Phase 3). The other 74 bits
 * come from 10 secure random bytes.
 *
 * How it connects: LogEntryFactory passes the entry's own time, so the id and
 * occurredAt always agree; tests supply known bytes, as with RequestIdGenerator.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Diagnostics;

use Closure;
use DateTimeInterface;
use LogicException;

final readonly class UuidGenerator
{
    private const int BYTES = 10;

    /** @var Closure(int<1, max>): string */
    private Closure $randomBytes;

    /**
     * @param (Closure(int<1, max>): string)|null $randomBytes where the bytes come from;
     *        PHP's secure random_bytes() unless a test supplies known bytes
     */
    public function __construct(?Closure $randomBytes = null)
    {
        $this->randomBytes = $randomBytes ?? random_bytes(...);
    }

    /** @throws LogicException when the byte source returns the wrong number of bytes */
    public function generate(DateTimeInterface $at): string
    {
        $random = ($this->randomBytes)(self::BYTES);
        if (strlen($random) !== self::BYTES) {
            throw new LogicException('The random byte source must return exactly ' . self::BYTES . ' bytes');
        }

        // 48 bits of Unix time in milliseconds ("U" seconds followed by "v" milliseconds).
        $milliseconds = str_pad(dechex((int) $at->format('Uv')), 12, '0', STR_PAD_LEFT);
        // Version 7 in the top 4 bits of the next 16; the variant (binary 10) in the top 2 bits after that.
        $versionAndRandom = sprintf('%04x', 0x7000 | (((ord($random[0]) << 8) | ord($random[1])) & 0x0FFF));
        $variantAndRandom = sprintf('%02x', 0x80 | (ord($random[2]) & 0x3F)) . bin2hex(substr($random, 3));

        return substr($milliseconds, 0, 8) . '-' . substr($milliseconds, 8) . '-' . $versionAndRandom
            . '-' . substr($variantAndRandom, 0, 4) . '-' . substr($variantAndRandom, 4);
    }
}
