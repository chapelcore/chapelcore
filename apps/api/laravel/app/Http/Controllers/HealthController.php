<?php

/**
 * HealthController.php — answers GET {apiBase}/health.
 *
 * Why: the core's HealthService runs the checks and works out the overall status; this
 * controller only sends the result, with 503 when it is down, so the deploy and uptime
 * monitors can read the HTTP status alone (DOMAIN-MODEL §4). A down report is still a
 * health response, never an error envelope.
 *
 * How it connects: routes/api.php points GET /health here; AppServiceProvider builds the
 * HealthService; ApiResponses writes the envelope.
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ApiResponses;
use ChapelCore\Core\Health\HealthService;
use Illuminate\Http\Response;

final readonly class HealthController
{
    public function __invoke(HealthService $health, ApiResponses $responses): Response
    {
        $status = $health->status();

        return $responses->success($status, $status->httpStatus());
    }
}
