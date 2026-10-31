<?php

/**
 * ExceptionInfo.php — an exception as written to the log (DOMAIN-MODEL §5.8).
 *
 * Why: when something fails, the log needs the exception's type, where it happened and
 * how the code got there, but never secrets. fromThrowable() redacts the message at
 * every level of the `previous` chain, writes `(no message)` for an empty one, and
 * builds trace frames without argument values, whatever the server's PHP settings.
 * It is never sent in a response; people only ever see a reference code.
 *
 * How it connects: LogEntry carries one; the Serializer writes it; the logger
 * (checkpoint 4b) creates it from a PSR-3 context's `exception`.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use ChapelCore\Core\Diagnostics\Redactor;
use InvalidArgumentException;
use Throwable;

final readonly class ExceptionInfo
{
    public const string NO_MESSAGE = '(no message)';

    /**
     * @param list<string> $trace one frame per line, most recent first
     * @throws InvalidArgumentException when a rule in DOMAIN-MODEL §5.8 is broken
     */
    public function __construct(
        public string $type,
        public string $message,
        public ?string $file,
        public ?int $line,
        public array $trace,
        public ?ExceptionInfo $previous,
    ) {
        Notation::text($type, 'ExceptionInfo.type');
        Notation::text($message, 'ExceptionInfo.message');
        if ($file !== null) {
            Notation::text($file, 'ExceptionInfo.file');
        }
        if ($line !== null && $line < 1) {
            throw new InvalidArgumentException("ExceptionInfo.line must be at least 1, got {$line}");
        }
        foreach ($trace as $frame) {
            Notation::text($frame, 'ExceptionInfo.trace (a frame)');
        }
    }

    public static function fromThrowable(Throwable $throwable, Redactor $redactor): self
    {
        $message = $throwable->getMessage();
        $previous = $throwable->getPrevious();

        return new self(
            $throwable::class,
            $message === '' ? self::NO_MESSAGE : $redactor->text($message),
            $throwable->getFile() === '' ? null : $throwable->getFile(),
            $throwable->getLine() >= 1 ? $throwable->getLine() : null,
            self::trace($throwable),
            $previous === null ? null : self::fromThrowable($previous, $redactor),
        );
    }

    /**
     * PHP's own getTraceAsString() lines, built from the frames without reading their
     * arguments: `#0 /path/File.php(18): Class->method()`, ending with `#N {main}`.
     *
     * @return list<string>
     */
    private static function trace(Throwable $throwable): array
    {
        $lines = [];
        foreach ($throwable->getTrace() as $number => $frame) {
            $where = isset($frame['file']) ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')' : '[internal function]';
            $lines[] = "#{$number} {$where}: " . ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '()';
        }
        $lines[] = '#' . count($lines) . ' {main}';

        return $lines;
    }
}
