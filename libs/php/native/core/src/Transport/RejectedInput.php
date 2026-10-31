<?php

/**
 * RejectedInput.php — thrown when a request body can't be accepted, carrying the error
 * to send back.
 *
 * Why: the parser decides which error code and which field messages a bad body gets;
 * the framework only sends them. Carrying a finished ApiError keeps that decision in
 * the core, so every backend answers a bad body the same way.
 *
 * How it connects: thrown by ClientErrorReportParser; Laravel (1.5) catches it and
 * sends $error through Serializer::errorEnvelope() with $error->code->httpStatus().
 */

declare(strict_types=1);

namespace ChapelCore\Core\Transport;

use ChapelCore\Core\Model\ApiError;
use RuntimeException;

final class RejectedInput extends RuntimeException
{
    public function __construct(public readonly ApiError $error)
    {
        parent::__construct($error->message);
    }
}
