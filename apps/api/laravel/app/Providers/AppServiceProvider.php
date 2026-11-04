<?php

/**
 * AppServiceProvider.php — tells Laravel how to build the app's objects.
 *
 * Why: the core's classes are plain PHP and know nothing about Laravel; this provider
 * is where they meet. register() binds the core's ports to their Laravel adapters (from
 * checkpoint 2); boot() runs once everything is registered.
 *
 * How it connects: listed in bootstrap/providers.php.
 */

declare(strict_types=1);

namespace App\Providers;

use App\Health\DatabaseConnectionPing;
use App\Health\HeartbeatFile;
use App\Http\CurrentRequestId;
use ChapelCore\Core\Clock;
use ChapelCore\Core\Health\DatabasePing;
use ChapelCore\Core\Health\DatabaseProbe;
use ChapelCore\Core\Health\HealthService;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Health\SchedulerProbe;
use ChapelCore\Core\Health\StorageProbe;
use ChapelCore\Core\SystemClock;
use ChapelCore\Core\Version\VersionReader;
use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // one CurrentRequestId per request (a scoped binding)
        $this->app->scoped(CurrentRequestId::class);

        // the core's ports, answered by Laravel adapters
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(DatabasePing::class, DatabaseConnectionPing::class);
        $this->app->bind(HeartbeatFile::class, fn (): HeartbeatFile => new HeartbeatFile(
            config()->string('chapelcore.heartbeat_path'),
        ));
        $this->app->bind(HeartbeatStore::class, HeartbeatFile::class);

        // /health: the version and the three checks, in the order DOMAIN-MODEL §4 lists them
        $this->app->bind(HealthService::class, fn (Application $app): HealthService => new HealthService(
            (new VersionReader(config()->string('chapelcore.version_path')))->read(),
            $app->make(DatabaseProbe::class),
            $app->make(SchedulerProbe::class),
            new StorageProbe($app->storagePath()),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AboutCommand::add('ChapelCore', fn (): array => [
            'Core' => self::installedVersion('chapelcore/core'),
            'Contract' => self::installedVersion('chapelcore/contract'),
        ]);
    }

    /**
     * Get the installed version of a package or "not installed".
     */
    private static function installedVersion(string $package): string
    {
        return InstalledVersions::isInstalled($package)
            ? (InstalledVersions::getPrettyVersion($package)
                ?? 'unknown')
            : 'not installed';
    }
}
