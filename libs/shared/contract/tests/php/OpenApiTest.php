<?php

/**
 * OpenApiTest.php — checks that openapi.json describes exactly the endpoints in
 * DOMAIN-MODEL.md, with the right schema for every request and response.
 *
 * Why: the PHP twin of tests/ts/openapi.test.ts. ENDPOINTS below repeats the list in
 * that file; because both tests read the same openapi.json, the two lists cannot drift
 * apart without one of the tests failing.
 *
 * How it connects: reads openapi.json, schemas/ (through Support.php) and
 * docs/design/DOMAIN-MODEL.md; runs with `composer test`.
 */

declare(strict_types=1);

namespace ChapelCore\Contract\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class OpenApiTest extends TestCase
{
    /**
     * Every endpoint in DOMAIN-MODEL: method, path, request body schema (or null), and
     * response status (or `default`) => body schema (or null for no body).
     */
    private const array ENDPOINTS = [
        [
            'method' => 'get',
            'path' => '/health',
            'requestBody' => null,
            // 503 still carries the health report, so the deploy check can read it (§4).
            'responses' => ['200' => 'health-response', '503' => 'health-response', 'default' => 'error-envelope'],
        ],
        [
            'method' => 'post',
            'path' => '/diagnostics/client-errors',
            'requestBody' => 'client-error-report',
            'responses' => ['202' => null, 'default' => 'error-envelope'],
        ],
    ];

    /** Keys of an OpenAPI path item that are operations; others (`summary`, `parameters`) are not. */
    private const array HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    private const string SCHEMA_REF = '#^schemas/([a-z0-9-]+)\.schema\.json$#';

    /** @return iterable<string, array{string, string, ?string, array<string, ?string>}> */
    public static function endpoints(): iterable
    {
        foreach (self::ENDPOINTS as $endpoint) {
            yield strtoupper($endpoint['method']) . ' ' . $endpoint['path'] => [
                $endpoint['method'],
                $endpoint['path'],
                $endpoint['requestBody'],
                $endpoint['responses'],
            ];
        }
    }

    public function testOpenApiJsonExists(): void
    {
        self::assertFileExists(self::openApiPath(), 'libs/shared/contract/openapi.json is missing');
    }

    public function testIsOpenApi31(): void
    {
        self::assertMatchesRegularExpression('/^3\.1\.\d+$/', (string) self::dig(self::doc(), 'openapi'));
    }

    public function testCarriesTheDomainModelVersionAsInfoVersion(): void
    {
        $model = (string) file_get_contents(Support::contractRoot() . '/../../../docs/design/DOMAIN-MODEL.md');
        self::assertSame(1, preg_match('/Model version: \*\*(\d+\.\d+)\*\*/', $model, $match), 'model version line in DOMAIN-MODEL.md');
        self::assertSame($match[1], self::dig(self::doc(), 'info', 'version'));
    }

    public function testEveryRefPointsAtAnExistingSchemaFile(): void
    {
        $names = array_keys(Support::loadSchemas());
        $broken = array_values(array_filter(
            self::refs(self::doc()),
            static fn(string $ref): bool => preg_match(self::SCHEMA_REF, $ref, $match) !== 1 || !in_array($match[1], $names, true),
        ));

        self::assertSame([], $broken);
    }

    public function testDocumentsExactlyTheEndpointsInTheDomainModel(): void
    {
        $documented = [];
        foreach (get_object_vars(self::objectOrEmpty(self::dig(self::doc(), 'paths'))) as $path => $item) {
            foreach (array_keys(get_object_vars(self::objectOrEmpty($item))) as $method) {
                if (in_array($method, self::HTTP_METHODS, true)) {
                    $documented[] = "{$method} {$path}";
                }
            }
        }
        $expected = array_map(static fn(array $e): string => "{$e['method']} {$e['path']}", self::ENDPOINTS);
        sort($documented);
        sort($expected);

        self::assertSame($expected, $documented);
    }

    #[DataProvider('endpoints')]
    public function testRequestBody(string $method, string $path, ?string $requestBody, array $responses): void
    {
        self::assertSame($requestBody, self::bodySchema(self::dig(self::doc(), 'paths', $path, $method, 'requestBody')));
    }

    /** @param array<string, ?string> $responses */
    #[DataProvider('endpoints')]
    public function testResponses(string $method, string $path, ?string $requestBody, array $responses): void
    {
        $documented = [];
        foreach (get_object_vars(self::objectOrEmpty(self::dig(self::doc(), 'paths', $path, $method, 'responses'))) as $status => $response) {
            $documented[(string) $status] = self::bodySchema($response);
        }
        ksort($documented);
        ksort($responses);

        self::assertSame($responses, $documented);
    }

    private static function openApiPath(): string
    {
        return Support::contractRoot() . '/openapi.json';
    }

    /** The parsed openapi.json; the test is skipped (not failed) when the file is missing, as in the TS test. */
    private static function doc(): stdClass
    {
        if (!is_file(self::openApiPath())) {
            self::markTestSkipped('openapi.json is missing (see testOpenApiJsonExists)');
        }
        $doc = Support::readJson(self::openApiPath());
        self::assertInstanceOf(stdClass::class, $doc);

        return $doc;
    }

    /** Follows a chain of keys into the document; null as soon as one is missing. */
    private static function dig(mixed $node, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            $node = $node instanceof stdClass ? ($node->{$key} ?? null) : null;
        }

        return $node;
    }

    private static function objectOrEmpty(mixed $node): stdClass
    {
        return $node instanceof stdClass ? $node : new stdClass();
    }

    /** The schema file a body points at (`schemas/<name>.schema.json` => `<name>`), or null if it has none. */
    private static function bodySchema(mixed $body): ?string
    {
        $ref = self::dig($body, 'content', 'application/json', 'schema', '$ref');
        if ($ref === null) {
            return null;
        }

        return is_string($ref) && preg_match(self::SCHEMA_REF, $ref, $match) === 1 ? $match[1] : 'unexpected $ref: ' . json_encode($ref);
    }

    /** @return list<string> every `$ref` in the document */
    private static function refs(mixed $node): array
    {
        if (!is_array($node) && !$node instanceof stdClass) {
            return [];
        }
        $refs = [];
        foreach ((array) $node as $key => $child) {
            if ($key === '$ref' && is_string($child)) {
                $refs[] = $child;
            } else {
                $refs = array_merge($refs, self::refs($child));
            }
        }

        return $refs;
    }
}
