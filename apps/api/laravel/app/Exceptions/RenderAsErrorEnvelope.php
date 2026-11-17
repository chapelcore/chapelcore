<?php

/**
 * RenderAsErrorEnvelope.php — turns any exception into the contract's error envelope.
 *
 * Why: the API answers only in JSON, and a failure must never show its message, class,
 * file or stack trace to the caller, even with debug on. Known HTTP errors (404, 405,
 * 429, 503) get their own code; everything else is INTERNAL_ERROR, with the details only
 * in the log. An HTTP error's own headers are kept: 429 carries Retry-After, 405 Allow.
 *
 * How it connects: registered as the render callback in bootstrap/app.php; builds the
 * response through ApiResponses. Logging the failure is Laravel's job (report), not this
 * class's.
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\ApiResponses;
use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\ErrorCode;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class RenderAsErrorEnvelope
{
    public function __construct(private ApiResponses $responses) {}

    public function __invoke(Throwable $exception): Response
    {
        $response = $this->responses->error(self::apiError($exception));
        if ($exception instanceof HttpExceptionInterface) {
            // e.g. Retry-After from the rate limiter
            $response->headers->add($exception->getHeaders());
        }

        return $response;
    }

    private static function apiError(Throwable $exception): ApiError
    {
        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        return match ($status) {
            404 => new ApiError(ErrorCode::NotFound, 'There is nothing at this address.'),
            405 => new ApiError(ErrorCode::MethodNotAllowed, 'This address does not accept that kind of request.'),
            429 => new ApiError(ErrorCode::RateLimited, 'Too many requests. Please wait a minute and try again.'),
            503 => new ApiError(ErrorCode::ServiceUnavailable, 'The service is down for maintenance. Please try again in a few minutes.'),
            default => new ApiError(ErrorCode::InternalError, 'Something went wrong on our side.'),
        };
    }
}
