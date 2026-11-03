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

use App\Http\CurrentRequestId;
use Composer\InstalledVersions;
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
