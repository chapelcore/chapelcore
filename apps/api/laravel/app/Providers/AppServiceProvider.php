<?php

/**
 * AppServiceProvider.php — tells Laravel how to build the app's objects.
 *
 * Why: the core's classes are plain PHP and know nothing about Laravel; this provider
 * is where they meet. register() binds the core's ports to their Laravel adapters (from
 * checkpoint 2) and builds the doctor from this app's settings; boot() runs once
 * everything is registered and defines the rate limits (CLAUDE.md: every public write
 * endpoint is rate-limited, keyed on the real client IP).
 *
 * How it connects: listed in bootstrap/providers.php. routes/api.php names the
 * client-errors limit (throttle:client-errors); config/cache.php keeps its counts in the
 * database, and config/trustedproxy.php decides which address $request->ip() returns.
 * The doctor's settings come from config/chapelcore.php; RunDoctor asks for the Doctor.
 */

declare(strict_types=1);

namespace App\Providers;

use App\Doctor\MariaDbCharsets;
use App\Doctor\TlsCertificateExpiries;
use App\Health\DatabaseConnectionPing;
use App\Health\HeartbeatFile;
use App\Http\CurrentRequestId;
use ChapelCore\Core\Clock;
use ChapelCore\Core\DatabaseDriver;
use ChapelCore\Core\Doctor\CertificateExpiries;
use ChapelCore\Core\Doctor\DatabaseCharsets;
use ChapelCore\Core\Doctor\Doctor;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Health\DatabasePing;
use ChapelCore\Core\Health\DatabaseProbe;
use ChapelCore\Core\Health\HealthService;
use ChapelCore\Core\Health\HeartbeatStore;
use ChapelCore\Core\Health\SchedulerProbe;
use ChapelCore\Core\Health\StorageProbe;
use ChapelCore\Core\SystemClock;
use ChapelCore\Core\Version\VersionReader;
use Composer\InstalledVersions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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

        // the doctor's environment: a mistyped CHAPELCORE_ENV stops it instead of passing as dev
        $this->app->bind(Environment::class, function (): Environment {
            $name = config()->string('chapelcore.env');

            return Environment::tryFrom($name)
                ?? throw new InvalidArgumentException("CHAPELCORE_ENV must be dev, test or production, not \"{$name}\"");
        });

        // the doctor's two ports: MariaDB only in Phase 1 (PostgreSQL in Phase 3, PLAN D20)
        $this->app->bind(DatabaseCharsets::class, MariaDbCharsets::class);
        $this->app->bind(CertificateExpiries::class, fn (): TlsCertificateExpiries => new TlsCertificateExpiries(
            array_values(array_filter(config()->array('chapelcore.tls_hosts'), is_string(...))),
        ));

        // the doctor: the nine Phase 1 checks, in DOMAIN-MODEL §5.8's order
        $this->app->bind(Doctor::class, fn (Application $app): Doctor => Doctor::phaseOne(
            $app->make(Environment::class),
            DatabaseDriver::MariaDb,
            $app->make(DatabaseCharsets::class),
            $app->storagePath(),
            $app->make(HeartbeatStore::class),
            config()->string('app.url'),
            config()->boolean('app.debug'),
            $app->make(CertificateExpiries::class),
            $app->make(Clock::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // browser error reports: 10 a minute per client IP (DOMAIN-MODEL §5.8)
        RateLimiter::for('client-errors', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->ip()));

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
