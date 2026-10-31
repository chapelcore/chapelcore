<?php

/**
 * DoctorProbe.php — one check the doctor can run.
 *
 * Why: each Phase 1 check (DOMAIN-MODEL §5.8) is a small class with one rule, so it can
 * be tested on its own with exact inputs. The probe decides; Laravel only supplies
 * values (configuration, a database connection, certificate dates).
 *
 * How it connects: Doctor runs a list of these in order; the PHP probes are in this
 * folder. It mirrors HealthProbe for the public health check.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use ChapelCore\Core\Model\DoctorCheck;

interface DoctorProbe
{
    /** The check's name (a Key), also used if check() throws. */
    public function name(): string;

    public function check(): DoctorCheck;
}
