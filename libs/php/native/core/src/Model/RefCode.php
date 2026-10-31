<?php

/**
 * RefCode.php — a reference code such as `7F3K-92QD` (DOMAIN-MODEL §2, RefCode).
 *
 * Why: the reference code ties together what a person sees on an error, the
 * X-Request-Id header and the log entry. It is an object rather than a plain string
 * so a malformed code can't exist anywhere in the code.
 *
 * How it connects: ResponseMeta carries it as requestId; RequestIdGenerator
 * (checkpoint 3) creates new ones; LogEntry (checkpoint 4) records it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class RefCode
{
    /** 8 Crockford Base32 characters (no I, L, O or U) in two groups of four (ref-code.schema.json). */
    public const string PATTERN = '/^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/D';

    /** @throws InvalidArgumentException when $value isn't a reference code */
    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException("RefCode must look like \"7F3K-92QD\", got \"{$value}\"");
        }
    }
}
