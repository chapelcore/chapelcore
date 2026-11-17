<?php

/**
 * ClientErrorController.php — answers POST {apiBase}/diagnostics/client-errors.
 *
 * Why: when the admin or the site hits an error in the browser, it sends a report here
 * so we can see it in the server log (DOMAIN-MODEL §5.8). The endpoint is public, so the
 * body is untrusted: the core's parser decides whether to accept it, exactly as sent or
 * not at all. This controller only reads the raw body, passes it on, logs an accepted
 * report and answers 202. A rejected report is answered and not logged.
 *
 * How it connects: routes/api.php points here, behind the client-errors rate limiter
 * (AppServiceProvider). The report goes to the client_errors log channel
 * (config/logging.php), which redacts it like every entry. ApiResponses writes the
 * replies.
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\ApiResponses;
use ChapelCore\Core\Transport\ClientErrorReportParser;
use ChapelCore\Core\Transport\RejectedInput;
use ChapelCore\Core\Transport\Serializer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final readonly class ClientErrorController
{
    public function __invoke(
        Request $request,
        ClientErrorReportParser $parser,
        Serializer $serializer,
        ApiResponses $responses,
    ): Response {
        try {
            // the raw body: $request->input() would already have trimmed strings and turned "" into null
            $report = $parser->parse($request->getContent());
        } catch (RejectedInput $rejected) {
            return $responses->error($rejected->error);
        }

        // the browser's message becomes the entry's message, the rest of the report is its context
        $context = $serializer->toTransport($report);
        unset($context['message']);
        Log::channel('client_errors')->warning($report->message, $context);

        return $responses->accepted();
    }
}
