<?php

/**
 * HealthCheck.php — one check in the public health report (DOMAIN-MODEL §4).
 *
 * Why: /health is public, so a check only says whether it passed, with a generic
 * message when it didn't. A passing check has no message at all (model 0.4).
 *
 * How it connects: HealthStatus holds a list of these; the probes in checkpoint 3
 * produce them.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class HealthCheck
{
    /** @throws InvalidArgumentException when a rule above is broken */
    public function __construct(
        public string $name,
        public Status $status,
        public ?string $message,
    ) {
        Notation::key($name, 'HealthCheck.name');
        if ($message !== null) {
            Notation::text($message, 'HealthCheck.message');
            if ($status === Status::Ok) {
                throw new InvalidArgumentException('HealthCheck.message must be null when the status is ok');
            }
        }
    }
}
