<?php

/**
 * HealthStatus.php — the body of GET {apiBase}/health (DOMAIN-MODEL §4).
 *
 * Why: the overall status is derived from the checks (the worst one wins), never
 * passed in, so it can't contradict them. httpStatus() gives the deploy pipeline its
 * signal: 200 for ok or degraded, 503 for down.
 *
 * How it connects: HealthService (checkpoint 3) builds one; the Serializer writes it;
 * Laravel (1.5) answers /health with it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

use InvalidArgumentException;

final readonly class HealthStatus
{
    public Status $status;

    /** @var list<HealthCheck> in the order given */
    public array $checks;

    /** @throws InvalidArgumentException when two checks share a name */
    public function __construct(public VersionInfo $version, HealthCheck ...$checks)
    {
        $names = array_map(static fn(HealthCheck $check): string => $check->name, $checks);
        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException('HealthStatus.checks must have unique names');
        }

        $this->checks = array_values($checks);
        $this->status = Status::worst(...array_map(static fn(HealthCheck $check): Status => $check->status, $this->checks));
    }

    public function httpStatus(): int
    {
        return $this->status === Status::Down ? 503 : 200;
    }
}
