<?php

/**
 * Serializer.php — turns model objects into transport JSON (DOMAIN-MODEL §8).
 *
 * Why: field names are converted to camelCase in exactly one place, here. Every field
 * is always written (null, never missing) and maps become stdClass, so an empty map is
 * `{}` rather than `[]`. Laravel API Resources, if any, call this and never rename
 * fields themselves.
 *
 * How it connects: Laravel (1.5) sends envelope() and errorEnvelope() as responses;
 * the tests check every shape against the contract schemas.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Transport;

use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\HealthStatus;
use ChapelCore\Core\Model\PageInfo;
use ChapelCore\Core\Model\ResponseMeta;
use ChapelCore\Core\Model\ServedBy;
use ChapelCore\Core\Model\VersionInfo;
use InvalidArgumentException;

final class Serializer
{
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * A success envelope: `{ data, meta }`.
     *
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     * @throws InvalidArgumentException when $data isn't part of the transport model
     */
    public function envelope(object $data, ResponseMeta $meta): array
    {
        return ['data' => $this->toTransport($data), 'meta' => $this->toTransport($meta)];
    }

    /**
     * An error envelope: `{ error, meta }`.
     *
     * @return array{error: array<string, mixed>, meta: array<string, mixed>}
     */
    public function errorEnvelope(ApiError $error, ResponseMeta $meta): array
    {
        return ['error' => $this->toTransport($error), 'meta' => $this->toTransport($meta)];
    }

    /**
     * One model object as transport data: camelCase keys, every field present.
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException when $value isn't part of the transport model
     */
    public function toTransport(object $value): array
    {
        return match (true) {
            $value instanceof VersionInfo => [
                'version' => $value->version,
                'codename' => $value->codename,
            ],
            $value instanceof ServedBy => [
                'backend' => $value->backend,
                'backendVersion' => $value->backendVersion,
                'language' => $value->language,
                'languageVersion' => $value->languageVersion,
            ],
            $value instanceof PageInfo => [
                'page' => $value->page,
                'perPage' => $value->perPage,
                'total' => $value->total,
            ],
            $value instanceof ResponseMeta => [
                'requestId' => $value->requestId->value,
                'modelVersion' => $value->modelVersion,
                'servedBy' => $this->toTransport($value->servedBy),
                'page' => $value->page === null ? null : $this->toTransport($value->page),
            ],
            $value instanceof HealthCheck => [
                'name' => $value->name,
                'status' => $value->status->value,
                'message' => $value->message,
            ],
            $value instanceof HealthStatus => [
                'status' => $value->status->value,
                'version' => $this->toTransport($value->version),
                'checks' => array_map($this->toTransport(...), $value->checks),
            ],
            $value instanceof ApiError => [
                'code' => $value->code->value,
                'message' => $value->message,
                // A map: stdClass, so no fields is written as {} rather than [].
                'fields' => (object) $value->fields,
            ],
            default => throw new InvalidArgumentException($value::class . ' is not part of the transport model'),
        };
    }

    /**
     * JSON text for transport data, without escaped slashes or Unicode.
     *
     * @param array<string, mixed> $payload
     */
    public function json(array $payload): string
    {
        return json_encode($payload, self::JSON_FLAGS);
    }
}
