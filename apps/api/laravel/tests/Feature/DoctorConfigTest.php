<?php

/**
 * DoctorConfigTest.php — the doctor's two settings, CHAPELCORE_ENV and
 * CHAPELCORE_TLS_HOSTS, are read from the environment as the doctor needs them.
 *
 * Why: CHAPELCORE_ENV names the environment for the doctor (E2; Laravel's APP_ENV stays
 * Laravel's), and CHAPELCORE_TLS_HOSTS lists the hosts whose certificates it checks, as
 * one comma-separated line in .env. Spaces and empty entries are dropped, the same way
 * as CHAPELCORE_CORS_ORIGINS. Settings are read once at boot, so this class sets them
 * before the app starts.
 *
 * How it connects: tests config/chapelcore.php. DoctorChecksTest covers what the doctor
 * does with the values.
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DoctorConfigTest extends TestCase
{
    protected array $bootEnvironment = [
        'CHAPELCORE_ENV' => 'production',
        'CHAPELCORE_TLS_HOSTS' => ' libbynaz.org, api.libbynaz.org,,example.org:8443 ',
    ];

    public function test_the_environment_name_is_read(): void
    {
        $this->assertSame('production', config('chapelcore.env'));
    }

    public function test_the_certificate_hosts_are_read_as_a_list(): void
    {
        $this->assertSame(['libbynaz.org', 'api.libbynaz.org', 'example.org:8443'], config('chapelcore.tls_hosts'));
    }
}
