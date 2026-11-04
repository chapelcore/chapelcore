<?php

/**
 * ApiResponses.php — builds the API's JSON responses, with their meta block.
 *
 * Why: every response has the same meta (the request's reference code and which
 * backend served it), and its JSON is written by the core's Serializer so it matches
 * the contract exactly. Controllers and the error renderer ask this class instead of
 * calling response()->json().
 *
 * How it connects: used by App\Exceptions\RenderAsErrorEnvelope (errors) and the
 * controllers (success); reads CurrentRequestId.
 */

declare(strict_types=1);

namespace App\Http;

use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\ResponseMeta;
use ChapelCore\Core\Model\ServedBy;
use ChapelCore\Core\Transport\Serializer;
use Illuminate\Foundation\Application;
use Illuminate\Http\Response;

final readonly class ApiResponses
{
    public function __construct(
        private CurrentRequestId $requestId,
        private Serializer $serializer,
    ) {}

    public function error(ApiError $error): Response
    {
        $payload = $this->serializer->errorEnvelope($error, $this->meta());

        return $this->json($payload, $error->code->httpStatus());
    }

    /** A success envelope around $data, a model object such as HealthStatus. */
    public function success(object $data, int $status = 200): Response
    {
        $payload = $this->serializer->envelope($data, $this->meta());

        return $this->json($payload, $status);
    }

    private function meta(): ResponseMeta
    {
        return new ResponseMeta(
            $this->requestId->get(),
            new ServedBy('laravel', Application::VERSION, 'php', PHP_VERSION),
        );
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status): Response
    {
        return new Response($this->serializer->json($payload), $status,
            ['Content-Type' => 'application/json']);
    }
}
