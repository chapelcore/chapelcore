<?php

/**
 * LogEntry.php — one line of the server's JSON log file (DOMAIN-MODEL §5.8).
 *
 * Why: a reference code shown to a person must lead to exactly one entry, with the
 * level, channel, message, context and exception that explain what happened. The
 * entry doesn't redact anything itself: the logger (checkpoint 4b) builds it from
 * redacted values, and the Serializer writes it as it is.
 *
 * How it connects: the Serializer turns it into one JSON line matching
 * log-entry.schema.json; requestId is the same RefCode as meta.requestId.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LogEntry
{
    /**
     * @param array<array-key, mixed> $context already redacted; [] when there is none
     * @throws InvalidArgumentException when a rule in DOMAIN-MODEL §5.8 is broken
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $occurredAt,
        public LogLevel $level,
        public string $channel,
        public string $message,
        public ?RefCode $requestId,
        public array $context,
        public ?ExceptionInfo $exception,
    ) {
        Notation::uuid($id, 'LogEntry.id');
        Notation::key($channel, 'LogEntry.channel');
        Notation::text($message, 'LogEntry.message');
    }
}
