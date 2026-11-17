<?php

/**
 * ApiPrefixTest.php — with CHAPELCORE_API_PREFIX=api, the routes move under /api.
 *
 * Why: the same build serves both layouts (PLAN D16): api.libbynaz.org/health with no
 * prefix, example.org/api/health with one. The prefix is read when the routes load, so
 * this class sets it before the app boots.
 *
 * How it connects: tests the route loading in bootstrap/app.php and the api_prefix
 * setting in config/chapelcore.php. HealthTest and SkeletonTest cover the no-prefix
 * layout.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ApiPrefixTest extends TestCase
{
    protected array $bootEnvironment = ['CHAPELCORE_API_PREFIX' => 'api'];

    public function test_health_is_served_under_the_prefix(): void
    {
        $response = $this->get('/api/health');

        $response->assertOk();
        $this->assertMatchesContract((string) $response->getContent(), 'health-response');
    }

    public function test_health_is_not_served_without_the_prefix(): void
    {
        $response = $this->get('/health');

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('error.code'));
    }

    public function test_only_the_prefixed_routes_are_served(): void
    {
        $this->assertSame(['GET|HEAD api/health', 'POST api/diagnostics/client-errors'], $this->servedRoutes());
    }
}
