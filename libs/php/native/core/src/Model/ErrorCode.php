<?php

/**
 * ErrorCode.php — the error codes defined so far and their HTTP statuses (DOMAIN-MODEL §4).
 *
 * Why: codes are stable and never change meaning; clients act on the code, people read
 * the message. Each phase adds its own codes here (the AUTH_* codes arrive in Phase 3).
 *
 * How it connects: ApiError carries one; Laravel (1.5) sends httpStatus() as the
 * response status.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

enum ErrorCode: string
{
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case PayloadTooLarge = 'PAYLOAD_TOO_LARGE';
    case RateLimited = 'RATE_LIMITED';
    case InternalError = 'INTERNAL_ERROR';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';

    public function httpStatus(): int
    {
        return match ($this) {
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::ValidationFailed => 422,
            self::PayloadTooLarge => 413,
            self::RateLimited => 429,
            self::InternalError => 500,
            self::ServiceUnavailable => 503,
        };
    }
}
