<?php

/**
 * SkeletonTest.php — checks the trimmed Laravel skeleton before any endpoint exists.
 *
 * Why: iteration 1.5 starts from the official Laravel template, trimmed to an API
 * (docs/plans/phase-1.md §6.3). These tests hold the trim to its promises: our two
 * packages are installed, the packages we removed stay removed, nothing is served that
 * we didn't write (not even Laravel's own /up and /storage/{path} routes), and the tests
 * run against a real MariaDB 11.4, the version on the host.
 *
 * How it connects: runs in `composer test` and the CI test-php jobs, which start a
 * MariaDB service. Checkpoint 4 adds the client-error route to the list.
 */

declare(strict_types=1);

namespace Tests\Feature;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Health\HealthService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SkeletonTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function removedPackages(): iterable
    {
        yield 'Tinker' => ['Laravel\Tinker\TinkerServiceProvider'];
        yield 'Sail' => ['Laravel\Sail\SailServiceProvider'];
        yield 'Pail' => ['Laravel\Pail\PailServiceProvider'];
        yield 'Pao' => ['Laravel\Pao\Laravel\ServiceProvider'];
        yield 'Collision' => ['NunoMaduro\Collision\Provider'];
        yield 'Mockery' => ['Mockery'];
        yield 'Faker' => ['Faker\Factory'];
    }

    public function test_the_core_and_the_contract_are_installed(): void
    {
        $this->assertTrue(class_exists(HealthService::class));
        $this->assertTrue(class_exists(ContractValidator::class));
    }

    #[DataProvider('removedPackages')]
    public function test_a_removed_package_stays_removed(string $class): void
    {
        $this->assertFalse(class_exists($class));
    }

    public function test_only_our_routes_are_served(): void
    {
        $this->assertSame(['GET|HEAD health'], $this->servedRoutes());
    }

    public function test_the_database_is_mariadb_11_4(): void
    {
        $version = DB::scalar('select version()');

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^11\.4\.\d+-MariaDB/', $version);
    }
}
