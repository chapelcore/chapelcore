<?php

/**
 * ClientErrorReport.php — a browser error sent by the admin or the site (DOMAIN-MODEL §5.8).
 *
 * Why: errors in the browser are invisible to the server unless the page reports them.
 * The limits keep a public, unauthenticated endpoint from filling the log: the path
 * never carries a query string or fragment (they can hold tokens), and every text has
 * a maximum length, counted in characters as JSON Schema counts them.
 *
 * How it connects: ClientErrorReportParser builds one from a request body and turns a
 * broken limit into field messages first; this constructor is the last guard. Laravel
 * (1.5) logs it through the Serializer, and the logger redacts it before writing.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ClientErrorReport
{
    public const int MAX_PATH = 2048;
    public const int MAX_MESSAGE = 1000;
    public const int MAX_STACK_LINES = 50;
    public const int MAX_STACK_LINE = 500;
    public const int MAX_USER_AGENT = 512;

    /**
     * @param list<string> $stack the browser's stack lines, in order; [] when unknown
     * @throws InvalidArgumentException when a limit in DOMAIN-MODEL §5.8 is broken
     */
    public function __construct(
        public ClientApp $app,
        public string $appVersion,
        public string $path,
        public string $message,
        public array $stack,
        public string $userAgent,
        public DateTimeImmutable $occurredAt,
    ) {
        Notation::semVer($appVersion, 'ClientErrorReport.appVersion');
        if (!str_starts_with($path, '/') || strpbrk($path, '?#') !== false) {
            throw new InvalidArgumentException('ClientErrorReport.path must start with / and have no query string or fragment');
        }
        self::limited($path, self::MAX_PATH, 'ClientErrorReport.path');
        self::limited(Notation::text($message, 'ClientErrorReport.message'), self::MAX_MESSAGE, 'ClientErrorReport.message');
        if (count($stack) > self::MAX_STACK_LINES) {
            throw new InvalidArgumentException('ClientErrorReport.stack must have at most ' . self::MAX_STACK_LINES . ' lines');
        }
        foreach ($stack as $index => $line) {
            self::limited(Notation::text($line, "ClientErrorReport.stack[{$index}]"), self::MAX_STACK_LINE, "ClientErrorReport.stack[{$index}]");
        }
        self::limited(Notation::text($userAgent, 'ClientErrorReport.userAgent'), self::MAX_USER_AGENT, 'ClientErrorReport.userAgent');
    }

    private static function limited(string $value, int $max, string $field): void
    {
        if (mb_strlen($value, 'UTF-8') > $max) {
            throw new InvalidArgumentException("{$field} must be at most {$max} characters");
        }
    }
}
