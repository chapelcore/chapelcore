<?php

/**
 * ClientErrorReportParser.php — reads a browser error report from a request body
 * (DOMAIN-MODEL §5.8).
 *
 * Why: POST {apiBase}/diagnostics/client-errors is public, so its body is untrusted.
 * A body over 8 KB is refused before it is decoded (PAYLOAD_TOO_LARGE). Otherwise every
 * field is checked against client-error-report.schema.json and all problems are
 * reported at once, each under its transport field name (VALIDATION_FAILED), so a
 * developer sees everything to fix in one response. Nothing is trimmed or redacted
 * here: a report is accepted exactly as sent, or not at all; the logger redacts.
 *
 * How it connects: returns a ClientErrorReport or throws RejectedInput with the
 * ApiError to send. Laravel (1.5) passes the raw body to parse(); the tests also call
 * fromTransport() with the contract fixtures.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Transport;

use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\ClientApp;
use ChapelCore\Core\Model\ClientErrorReport;
use ChapelCore\Core\Model\ErrorCode;
use ChapelCore\Core\Model\Notation;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use stdClass;

final class ClientErrorReportParser
{
    /** The largest body accepted, in bytes (8 KB). */
    public const int MAX_BYTES = 8192;

    private const array FIELDS = ['app', 'appVersion', 'path', 'message', 'stack', 'userAgent', 'occurredAt'];

    /** @throws RejectedInput when the body is too large, isn't a JSON object, or breaks a field rule */
    public function parse(string $body): ClientErrorReport
    {
        if (strlen($body) > self::MAX_BYTES) {
            throw new RejectedInput(new ApiError(ErrorCode::PayloadTooLarge, 'The error report is larger than 8 KB.'));
        }
        try {
            $data = json_decode($body, false, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw self::notAnObject();
        }

        return $this->fromTransport($data);
    }

    /**
     * A report from already-decoded JSON (objects as stdClass).
     *
     * @throws RejectedInput when $data isn't an object or breaks a field rule
     */
    public function fromTransport(mixed $data): ClientErrorReport
    {
        if (!$data instanceof stdClass) {
            throw self::notAnObject();
        }
        $values = get_object_vars($data);
        $problems = [];

        $app = self::app($values, $problems);
        $appVersion = self::appVersion($values, $problems);
        $path = self::path($values, $problems);
        $message = self::text($values, 'message', ClientErrorReport::MAX_MESSAGE, $problems);
        $stack = self::stack($values, $problems);
        $userAgent = self::text($values, 'userAgent', ClientErrorReport::MAX_USER_AGENT, $problems);
        $occurredAt = self::occurredAt($values, $problems);
        foreach (array_diff(array_keys($values), self::FIELDS) as $unknown) {
            $problems[$unknown] = ['Not part of an error report.'];
        }

        if ($app === null || $appVersion === null || $path === null || $message === null || $stack === null
            || $userAgent === null || $occurredAt === null || $problems !== []) {
            throw new RejectedInput(new ApiError(ErrorCode::ValidationFailed, 'The error report was not accepted.', $problems));
        }

        return new ClientErrorReport($app, $appVersion, $path, $message, $stack, $userAgent, $occurredAt);
    }

    private static function notAnObject(): RejectedInput
    {
        return new RejectedInput(new ApiError(ErrorCode::ValidationFailed, 'The error report must be a JSON object.'));
    }

    /**
     * The field as a string, or null after recording why it isn't one.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function string(array $values, string $field, array &$problems): ?string
    {
        if (!array_key_exists($field, $values)) {
            $problems[$field] = ['Required.'];

            return null;
        }
        if (!is_string($values[$field])) {
            $problems[$field] = ['Must be text.'];

            return null;
        }

        return $values[$field];
    }

    /**
     * Non-empty text of at most $max characters.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function text(array $values, string $field, int $max, array &$problems): ?string
    {
        $value = self::string($values, $field, $problems);
        if ($value === null) {
            return null;
        }
        $problem = self::textProblem($value, $max);
        if ($problem !== null) {
            $problems[$field] = [$problem];

            return null;
        }

        return $value;
    }

    private static function textProblem(string $value, int $max): ?string
    {
        return match (true) {
            $value === '' => 'Must not be empty.',
            mb_strlen($value, 'UTF-8') > $max => "Must be at most {$max} characters.",
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function app(array $values, array &$problems): ?ClientApp
    {
        $value = self::string($values, 'app', $problems);
        if ($value === null) {
            return null;
        }
        $app = ClientApp::tryFrom($value);
        if ($app === null) {
            $problems['app'] = ['Must be "admin" or "web".'];
        }

        return $app;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function appVersion(array $values, array &$problems): ?string
    {
        $value = self::string($values, 'appVersion', $problems);
        if ($value !== null && preg_match(Notation::SEM_VER, $value) !== 1) {
            $problems['appVersion'] = ['Must be a version number such as 1.0.0.'];

            return null;
        }

        return $value;
    }

    /**
     * Every broken path rule is reported, so one response lists them all.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function path(array $values, array &$problems): ?string
    {
        $value = self::string($values, 'path', $problems);
        if ($value === null) {
            return null;
        }
        $messages = [];
        if (!str_starts_with($value, '/')) {
            $messages[] = 'Must start with /.';
        }
        if (strpbrk($value, '?#') !== false) {
            $messages[] = 'Must not include a query string (?) or fragment (#).';
        }
        if (mb_strlen($value, 'UTF-8') > ClientErrorReport::MAX_PATH) {
            $messages[] = 'Must be at most ' . ClientErrorReport::MAX_PATH . ' characters.';
        }
        if ($messages !== []) {
            $problems['path'] = $messages;

            return null;
        }

        return $value;
    }

    /**
     * Lines are numbered from 1 in messages, as people count them.
     *
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     * @return list<string>|null
     */
    private static function stack(array $values, array &$problems): ?array
    {
        if (!array_key_exists('stack', $values)) {
            $problems['stack'] = ['Required.'];

            return null;
        }
        $lines = $values['stack'];
        if (!is_array($lines) || !array_is_list($lines)) {
            $problems['stack'] = ['Must be a list of lines.'];

            return null;
        }
        $messages = [];
        if (count($lines) > ClientErrorReport::MAX_STACK_LINES) {
            $messages[] = 'Must have at most ' . ClientErrorReport::MAX_STACK_LINES . ' lines.';
        }
        $stack = [];
        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $problem = is_string($line) ? self::textProblem($line, ClientErrorReport::MAX_STACK_LINE) : 'Must be text.';
            if ($problem !== null) {
                $messages[] = "Line {$number}: {$problem}";
            } elseif (is_string($line)) {
                $stack[] = $line;
            }
        }
        if ($messages !== []) {
            $problems['stack'] = $messages;

            return null;
        }

        return $stack;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param array<array-key, list<string>> $problems
     */
    private static function occurredAt(array $values, array &$problems): ?DateTimeImmutable
    {
        $value = self::string($values, 'occurredAt', $problems);
        if ($value === null) {
            return null;
        }
        try {
            return Timestamp::parse($value);
        } catch (InvalidArgumentException) {
            $problems['occurredAt'] = ['Must be a UTC time such as 2026-10-21T15:04:05.123Z.'];

            return null;
        }
    }
}
