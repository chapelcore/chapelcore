<?php

/**
 * DoctorCheck.php — one line of the doctor's report (DOMAIN-MODEL §5.8).
 *
 * Why: the doctor explains what is wrong with a server and how to fix it, in plain
 * words. It is never exposed on a public endpoint, so unlike a HealthCheck its message
 * may name versions, paths and extensions, even when the check passes ("PHP 8.5.11").
 * A passing check has no fix.
 *
 * How it connects: the probes in src/Doctor create these; DoctorReport collects them;
 * the Serializer writes them (doctor-check.schema.json).
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class DoctorCheck
{
    /** @throws InvalidArgumentException when a rule above is broken */
    public function __construct(
        public string $name,
        public Status $status,
        public ?string $message,
        public ?string $fix,
    ) {
        Notation::key($name, 'DoctorCheck.name');
        if ($message !== null) {
            Notation::text($message, 'DoctorCheck.message');
        }
        if ($fix !== null) {
            Notation::text($fix, 'DoctorCheck.fix');
            if ($status === Status::Ok) {
                throw new InvalidArgumentException('DoctorCheck.fix must be null when the status is ok');
            }
        }
    }
}
