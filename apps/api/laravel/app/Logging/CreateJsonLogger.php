<?php

/**
 * CreateJsonLogger.php — builds the core's JsonLogger for Laravel's "custom" log driver.
 *
 * Why: the core's logger is plain PHP and doesn't know about Laravel's container or the
 * current request. This factory gives it the folder from config/logging.php and a way to
 * ask for the request's reference code at the moment each entry is written.
 *
 * How it connects: named as `via` in config/logging.php; Laravel calls it with that
 * channel's config and wraps the result in its own Logger (Log::…).
 */

declare(strict_types=1);

namespace App\Logging;

use App\Http\CurrentRequestId;
use ChapelCore\Core\Diagnostics\JsonLogger;
use ChapelCore\Core\Diagnostics\LogEntryFactory;
use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Diagnostics\UuidGenerator;
use ChapelCore\Core\Model\RefCode;
use ChapelCore\Core\SystemClock;
use Illuminate\Contracts\Container\Container;

final readonly class CreateJsonLogger
{
    public function __construct(private Container $container) {}

    /** @param array{path: string, channel: string} $config */
    public function __invoke(array $config): JsonLogger
    {
        return new JsonLogger(
            $config['path'],
            new LogEntryFactory(new SystemClock, new UuidGenerator, new Redactor),
            $config['channel'],
            fn (): ?RefCode => $this->container->make(CurrentRequestId::class)->ifAssigned(),
        );
    }
}
