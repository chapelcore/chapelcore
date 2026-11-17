<?php

/**
 * CurrentRequestId.php — holds the reference code of the request being served.
 *
 * Why: one code ties together what the caller sees (the X-Request-Id header and
 * meta.requestId) and what we log, so "my reference code is 7F3K-92QD" finds the log
 * line (DOMAIN-MODEL §6). The code is made here, never taken from the caller (E3).
 *
 * How it connects: registered as a scoped binding in AppServiceProvider (one per
 * request). AssignRequestId gives each request a new code; ApiResponses puts it in
 * meta; CreateJsonLogger reads it for each log entry, and gets null outside a request.
 */

declare(strict_types=1);

namespace App\Http;

use ChapelCore\Core\Diagnostics\RequestIdGenerator;
use ChapelCore\Core\Model\RefCode;

/**
 * No readonly on this class, unlike the other classes: $id is mutable, because it changes
 * per request. The generator is readonly, because it is injected once and never changes.
 */
final class CurrentRequestId
{
    private ?RefCode $id = null;

    public function __construct(private readonly RequestIdGenerator $generator) {}

    public function assignNew(): RefCode
    {
        return $this->id = $this->generator->generate();
    }

    public function get(): RefCode
    {
        return $this->id ?? $this->assignNew();
    }

    public function ifAssigned(): ?RefCode
    {
        return $this->id;
    }
}
