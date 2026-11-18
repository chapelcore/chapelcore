<?php

/**
 * Redactor.php — removes secrets and personal data before anything is logged.
 *
 * Why: CLAUDE.md, Logging: passwords, tokens, secret values, prayer text and personal
 * data are redacted before anything is written. This is the one pure function that
 * does it (DOMAIN-MODEL §5.8, Redaction): rule 1 replaces whole values by key, rule 2
 * replaces patterns in text. Running it twice gives the same result as running it once.
 * When PHP's regular-expression engine gives up on a string (a backtracking or JIT stack
 * limit), the whole string becomes [unredactable]: never partly redacted, never empty.
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

    /** What a whole string becomes when a rule 2 pattern can't be applied to it. */
    public const string UNREDACTABLE = '[unredactable]';

    /** Rule 1: a key is secret when its lowercased form, without `_`, `-` and `.`, contains one of these. */
    private const array SECRET_WORDS = [
        'password', 'passwd', 'secret', 'token', 'apikey', 'authorization', 'cookie', 'session', 'csrf', 'prayer',
    ];

    /** Rule 2a: `Bearer` and a token (RFC 6750 token characters). */
    private const string BEARER = '/\bBearer\s+[A-Za-z0-9\-._~+\/]+=*/i';

    /** Rule 2b: the password in a URL, `scheme://user:password@` (the user may be empty, as in Redis URLs). */
    private const string URL_PASSWORD = '/\b([A-Za-z][A-Za-z0-9+.\-]*:\/\/[^\s:\/@]*):[^\s\/]+@/';

    /** Rule 2c: one query parameter, `?name=value` or `&name=value`. */
    private const string QUERY_PARAMETER = '/([?&])([^=&#\s]+)=([^&#\s]*)/';

    /** Rule 2d: an email address. */
    private const string EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/';

    /** Rule 2e: a standalone run of 32 or more base64 / base64url characters (hex is a subset). */
    private const string TOKEN_CANDIDATE = '/(?<![A-Za-z0-9+\/_\-])[A-Za-z0-9+\/_\-]{32,}={0,2}(?![A-Za-z0-9+\/_=\-])/';

    /** Rule 2f: a North American phone number: 406-293-1234, (406) 293 1234, 406.293.1234. */
    private const string PHONE = '/(?<!\d)(?:\(\d{3}\)\s?|\d{3}[\-.\s])\d{3}[\-.\s]\d{4}(?!\d)/';

    /** Rule 2g: a run of 7 or more digits. */
    private const string DIGITS = '/\d{7,}/';

    /**
     * Rule 2, in order: Bearer, URL passwords, query values, email, token, phone, digits.
     * Each step returns null when PCRE gives up, and then the whole text is unredactable.
     */
    public function text(string $text): string
    {
        $steps = [
            static fn(string $t): ?string => preg_replace(self::BEARER, 'Bearer ' . self::REDACTED, $t),
            static fn(string $t): ?string => preg_replace(self::URL_PASSWORD, '$1:' . self::REDACTED . '@', $t),
            static fn(string $t): ?string => preg_replace_callback(
                self::QUERY_PARAMETER,
                static fn(array $m): string => self::isSecretKey($m[2]) ? "{$m[1]}{$m[2]}=" . self::REDACTED : $m[0],
                $t,
            ),
            static fn(string $t): ?string => preg_replace(self::EMAIL, '[email]', $t),
            static fn(string $t): ?string => preg_replace_callback(
                self::TOKEN_CANDIDATE,
                static fn(array $m): string => self::looksLikeToken($m[0]) ? '[token]' : $m[0],
                $t,
            ),
            static fn(string $t): ?string => preg_replace(self::PHONE, '[number]', $t),
            static fn(string $t): ?string => preg_replace(self::DIGITS, '[number]', $t),
        ];
        foreach ($steps as $step) {
            $text = $step($text);
            if ($text === null) {
                return self::UNREDACTABLE;
            }
        }

        return $text;
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

    /**
     * All hex, or a digit, an uppercase and a lowercase letter (so long words and paths are kept).
     * Plain string checks, not regular expressions, so this can't fail and keep a token.
     */
    private static function looksLikeToken(string $run): bool
    {
        if (ctype_xdigit($run)) {
            return true;
        }

        return strpbrk($run, '0123456789') !== false
            && strpbrk($run, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') !== false
            && strpbrk($run, 'abcdefghijklmnopqrstuvwxyz') !== false;
    }
}
