<?php

/**
 * LogEntryFactory.php — turns one PSR-3 log call into a redacted LogEntry.
 *
 * Why: this is where the logging rules of DOMAIN-MODEL §5.8 meet. The message and
 * context are always redacted; a Throwable under `exception` (the PSR-3 convention)
 * becomes the entry's ExceptionInfo; everything else in the context becomes plain JSON
 * data first (dates, enums, exceptions, other objects, resources, very deep arrays), so
 * writing the line never fails and no object carries text past the Redactor. Objects
 * other than these are written as their class name only, never their contents.
 *
 * How it connects: JsonLogger calls create() for every log call; it uses the
 * UuidGenerator for the id, the Clock for the time and the Redactor for the rules.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Diagnostics;

use BackedEnum;
use ChapelCore\Core\Clock;
use ChapelCore\Core\Model\ExceptionInfo;
use ChapelCore\Core\Model\LogEntry;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Transport\Serializer;
use ChapelCore\Core\Transport\Timestamp;
use DateTimeInterface;
use Stringable;
use Throwable;

final readonly class LogEntryFactory
{
    /** Arrays nested deeper than this inside the context are cut off. */
    private const int MAX_DEPTH = 10;

    private const string TOO_DEEP = '[too deep]';

    private Serializer $serializer;

    public function __construct(
        private Clock $clock,
        private UuidGenerator $uuids,
        private Redactor $redactor,
    ) {
        $this->serializer = new Serializer();
    }

    /** @param array<array-key, mixed> $context a PSR-3 context */
    public function create(
        LogLevel $level,
        string $channel,
        string|Stringable $message,
        array $context,
        ?RefCode $requestId,
    ): LogEntry {
        $now = $this->clock->now();
        $exception = null;
        if (($context['exception'] ?? null) instanceof Throwable) {
            $exception = ExceptionInfo::fromThrowable($context['exception'], $this->redactor);
            unset($context['exception']);
        }
        $text = (string) $message;

        return new LogEntry(
            $this->uuids->generate($now),
            $now,
            $level,
            $channel,
            $text === '' ? ExceptionInfo::NO_MESSAGE : $this->redactor->text($text),
            $requestId,
            $this->redactor->context($this->plainArray($context, 1)),
            $exception,
        );
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function plainArray(array $values, int $depth): array
    {
        return array_map(fn(mixed $value): mixed => $this->plain($value, $depth), $values);
    }

    private function plain(mixed $value, int $depth): mixed
    {
        return match (true) {
            is_array($value) => $depth > self::MAX_DEPTH ? self::TOO_DEEP : $this->plainArray($value, $depth + 1),
            // JSON has no NAN or INF, so they are written as text (PHP 8.5 warns on a plain cast).
            is_float($value) && is_nan($value) => 'NAN',
            is_float($value) && is_infinite($value) => $value > 0 ? 'INF' : '-INF',
            is_scalar($value), $value === null => $value,
            $value instanceof Throwable => $this->serializer->toTransport(ExceptionInfo::fromThrowable($value, $this->redactor)),
            $value instanceof DateTimeInterface => Timestamp::format($value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof Stringable => (string) $value,
            is_object($value) => '[object ' . $value::class . ']',
            default => '[resource]',
        };
    }
}
