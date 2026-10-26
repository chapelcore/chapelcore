<?php

/**
 * Redactor.php — removes secrets and personal data before anything is logged.
 *
 * Why: CLAUDE.md, Logging: passwords, tokens, secret values, prayer text and personal
 * data are redacted before anything is written. This is the one pure function that
 * does it (DOMAIN-MODEL §5.8, Redaction): rule 1 replaces whole values by key, rule 2
 * replaces patterns in text. Running it twice gives the same result as running it once.
 *
 * How it connects: ExceptionInfo::fromThrowable() redacts exception messages with it;
 * the logger (checkpoint 4b) redacts every message and context. It expects plain data
 * (strings, numbers, booleans, null and arrays); the logger converts objects first. The
 * shared cases in libs/shared/contract/redaction are its definitive examples.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Diagnostics;

final readonly class Redactor
{
    public const string REDACTED = '[redacted]';

    /** Rule 1: a key is secret when its lowercased form, without `_`, `-` and `.`, contains one of these. */
    private const array SECRET_WORDS = [
        'password', 'passwd', 'secret', 'token', 'apikey', 'authorization', 'cookie', 'session', 'csrf', 'prayer',
    ];

    /** Rule 2a: `Bearer` and a token (RFC 6750 token characters). */
    private const string BEARER = '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i';

    /** Rule 2b: one query parameter, `?name=value` or `&name=value`. */
    private const string QUERY_PARAMETER = '/([?&])([^=&#\s]+)=([^&#\s]*)/';

    /** Rule 2c: an email address. */
    private const string EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/';

    /** Rule 2d: a standalone run of 32 or more base64 / base64url characters (hex is a subset). */
    private const string TOKEN_CANDIDATE = '/(?<![A-Za-z0-9+\/_\-])[A-Za-z0-9+\/_\-]{32,}={0,2}(?![A-Za-z0-9+\/_=\-])/';

    /** Rule 2e: a North American phone number: 406-293-1234, (406) 293 1234, 406.293.1234. */
    private const string PHONE = '/(?<!\d)(?:\(\d{3}\)\s?|\d{3}[\-.\s])\d{3}[\-.\s]\d{4}(?!\d)/';

    /** Rule 2f: a run of 7 or more digits. */
    private const string DIGITS = '/\d{7,}/';

    /** Rule 2, in order: Bearer, query values, email, token, phone, digits. */
    public function text(string $text): string
    {
        $text = (string) preg_replace(self::BEARER, 'Bearer ' . self::REDACTED, $text);
        $text = (string) preg_replace_callback(
            self::QUERY_PARAMETER,
            static fn(array $m): string => self::isSecretKey($m[2]) ? "{$m[1]}{$m[2]}=" . self::REDACTED : $m[0],
            $text,
        );
        $text = (string) preg_replace(self::EMAIL, '[email]', $text);
        $text = (string) preg_replace_callback(
            self::TOKEN_CANDIDATE,
            static fn(array $m): string => self::looksLikeToken($m[0]) ? '[token]' : $m[0],
            $text,
        );
        $text = (string) preg_replace(self::PHONE, '[number]', $text);

        return (string) preg_replace(self::DIGITS, '[number]', $text);
    }

    /**
     * Rule 1 at every depth (including inside lists), and rule 2 for every other string.
     * Numbers, booleans and null under other keys are kept.
     *
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    public function context(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            $redacted[$key] = is_string($key) && self::isSecretKey($key) ? self::REDACTED : $this->value($value);
        }

        return $redacted;
    }

    private function value(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => $this->text($value),
            is_array($value) => $this->context($value),
            default => $value,
        };
    }

    private static function isSecretKey(string $key): bool
    {
        $normalized = str_replace(['_', '-', '.'], '', strtolower($key));
        foreach (self::SECRET_WORDS as $word) {
            if (str_contains($normalized, $word)) {
                return true;
            }
        }

        return false;
    }

    /** All hex, or a digit, an uppercase and a lowercase letter (so long words and paths are kept). */
    private static function looksLikeToken(string $run): bool
    {
        if (ctype_xdigit($run)) {
            return true;
        }

        return preg_match('/\d/', $run) === 1 && preg_match('/[A-Z]/', $run) === 1 && preg_match('/[a-z]/', $run) === 1;
    }
}
