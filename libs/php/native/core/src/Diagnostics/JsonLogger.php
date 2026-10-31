<?php

/**
 * JsonLogger.php — the PSR-3 logger that writes the server's JSON log.
 *
 * Why: CLAUDE.md, Logging: everything is logged through the logging port (PSR-3 in
 * PHP), and nothing is written before it is redacted. Each call appends one LogEntry,
 * as one JSON line, to `chapelcore-YYYY-MM-DD.log` (UTC date) in the log folder. A log
 * call never throws because of what was logged: if the file can't be written, PHP's own
 * error log gets a short note naming the file, never the entry's content.
 *
 * How it connects: LogEntryFactory builds each entry and the Serializer writes it.
 * Laravel (1.5) uses this class as its log channel, passing the folder, the channel
 * name and a closure that returns the current request's reference code.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Diagnostics;

use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Model\Notation;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\Transport\Serializer;
use Closure;
use DateTimeZone;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Stringable;

final class JsonLogger extends AbstractLogger
{
    /** @var Closure(): ?RefCode */
    private readonly Closure $requestId;

    private readonly Serializer $serializer;

    /**
     * @param string $directory the folder for the daily files; it must already exist
     * @param string $channel a Key such as `app` or `diagnostics.client`
     * @param (Closure(): ?RefCode)|null $requestId the current request's reference code,
     *        asked for on every call; null outside a request (always null if omitted)
     * @throws \InvalidArgumentException when $channel isn't a Key
     */
    public function __construct(
        private readonly string $directory,
        private readonly LogEntryFactory $entries,
        private readonly string $channel = 'app',
        ?Closure $requestId = null,
    ) {
        Notation::key($channel, 'JsonLogger channel');
        $this->requestId = $requestId ?? static fn(): ?RefCode => null;
        $this->serializer = new Serializer();
    }

    /**
     * @param mixed $level one of the eight PSR-3 levels
     * @param array<array-key, mixed> $context
     * @throws InvalidArgumentException when $level isn't a PSR-3 level (PSR-3 §1.1)
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $logLevel = is_string($level) || $level instanceof Stringable ? LogLevel::tryFrom((string) $level) : null;
        if ($logLevel === null) {
            throw new InvalidArgumentException('Unknown log level; use one of the eight PSR-3 levels');
        }

        $entry = $this->entries->create($logLevel, $this->channel, $message, $context, ($this->requestId)());
        $line = $this->serializer->json($this->serializer->toTransport($entry)) . "\n";
        $file = $this->directory . '/chapelcore-'
            . $entry->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d') . '.log';

        // LOCK_EX keeps lines from two requests writing at once from mixing.
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log("ChapelCore could not write to the log file {$file}");
        }
    }
}
