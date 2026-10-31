<?php

/**
 * Status.php — ok, degraded or down: the state of a health or doctor check.
 *
 * Why: the overall status is always the worst of the checks (DOMAIN-MODEL §4), and the
 * deploy pipeline acts on it. worst() is the one place that ordering is written.
 *
 * How it connects: HealthCheck and HealthStatus use it now; DoctorCheck in checkpoint 5.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

enum Status: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Down = 'down';

    /** The worst of the given statuses; Ok when there are none. */
    public static function worst(self ...$statuses): self
    {
        $worst = self::Ok;
        foreach ($statuses as $status) {
            if ($status->severity() > $worst->severity()) {
                $worst = $status;
            }
        }

        return $worst;
    }

    private function severity(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Degraded => 1,
            self::Down => 2,
        };
    }
}
