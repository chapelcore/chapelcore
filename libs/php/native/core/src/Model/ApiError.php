<?php

/**
 * ApiError.php — the error part of an error envelope (DOMAIN-MODEL §4).
 *
 * Why: the message is shown to people, so it is never empty; field messages tell them
 * what to fix, so each field has at least one non-empty message. A stack trace never
 * belongs here; it goes to the log (checkpoint 4).
 *
 * How it connects: the Serializer wraps it with ResponseMeta; Laravel (1.5) turns
 * exceptions into one; ClientErrorReportParser (checkpoint 6) fills in fields.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class ApiError
{
    /**
     * @param array<array-key, list<string>> $fields messages per field, in order; [] when none.
     *        A numeric field name such as "0" is an int key in a PHP array.
     * @throws InvalidArgumentException when the message or a field message is empty
     */
    public function __construct(
        public ErrorCode $code,
        public string $message,
        public array $fields = [],
    ) {
        Notation::text($message, 'ApiError.message');
        foreach ($fields as $field => $messages) {
            Notation::text((string) $field, 'ApiError.fields (a field name)');
            if ($messages === []) {
                throw new InvalidArgumentException("ApiError.fields.{$field} must have at least one message");
            }
            foreach ($messages as $text) {
                Notation::text($text, "ApiError.fields.{$field}");
            }
        }
    }
}
