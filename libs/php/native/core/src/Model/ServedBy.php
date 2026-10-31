<?php

/**
 * ServedBy.php — which backend and language answered a request (DOMAIN-MODEL §4).
 *
 * Why: ChapelCore can run on more than one backend, so every response names the one
 * that served it. The values come from runtime introspection, never literals
 * (DOMAIN-MODEL rule 12).
 *
 * How it connects: ResponseMeta carries it; Laravel (1.5) fills it in.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class ServedBy
{
    /** @throws InvalidArgumentException when any value is empty */
    public function __construct(
        public string $backend,
        public string $backendVersion,
        public string $language,
        public string $languageVersion,
    ) {
        Notation::text($backend, 'ServedBy.backend');
        Notation::text($backendVersion, 'ServedBy.backendVersion');
        Notation::text($language, 'ServedBy.language');
        Notation::text($languageVersion, 'ServedBy.languageVersion');
    }
}
