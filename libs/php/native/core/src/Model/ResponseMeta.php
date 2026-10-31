<?php

/**
 * ResponseMeta.php — the meta block of every response (DOMAIN-MODEL §4).
 *
 * Why: every response, success or error, carries its reference code, the model version
 * and the backend that served it. modelVersion isn't a parameter: it is always the
 * version this code implements.
 *
 * How it connects: the Serializer writes it into both envelopes; Laravel (1.5) builds
 * one per request.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use ChapelCore\Core\Version\ModelVersion;

final readonly class ResponseMeta
{
    public string $modelVersion;

    public function __construct(
        public RefCode $requestId,
        public ServedBy $servedBy,
        public ?PageInfo $page = null,
    ) {
        $this->modelVersion = ModelVersion::CURRENT;
    }
}
