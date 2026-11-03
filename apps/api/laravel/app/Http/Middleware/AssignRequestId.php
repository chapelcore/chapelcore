<?php

/**
 * AssignRequestId.php — gives every request a new reference code and returns it in the
 * X-Request-Id header.
 *
 * Why: every response, success or error, carries the code the caller can quote to us.
 * It runs first, so even a request refused by maintenance mode gets a code.
 *
 * How it connects: prepended to the global middleware in bootstrap/app.php; stores the
 * code in CurrentRequestId, where ApiResponses and the logger read it.
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\CurrentRequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Readonly on this class makes every property readonly: they are injected once and
 * never change. Final means the class can't be extended; it isn't meant to be.
 */
final readonly class AssignRequestId
{
    public function __construct(private CurrentRequestId $requestId) {}

    /** @param Closure(Request):Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $this->requestId->assignNew();
        $response = $next($request);
        $response->headers->set('X-Request-Id', $code->value);

        return $response;
    }
}
