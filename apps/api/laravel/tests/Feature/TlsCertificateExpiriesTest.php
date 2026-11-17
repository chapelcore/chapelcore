<?php

/**
 * TlsCertificateExpiriesTest.php — App\Doctor\TlsCertificateExpiries connects to each
 * host and reads when its certificate expires.
 *
 * Why: the doctor's tls.certificate check (DOMAIN-MODEL §5.8) warns 30 days before a
 * certificate expires, because a missed acme.sh renewal takes the site down (phase-1
 * R10). The adapter only reads the date, so it reads it even from a certificate a
 * browser wouldn't trust (self-signed, or already expired): "expired on …" says more than
 * "could not connect". A host it can't reach is null, which the check reports as down.
 *
 * How it connects: tests the adapter for the core's CertificateExpiries port against a
 * local TLS server with a certificate made for the test (Tests\Support\LocalTlsServer).
 * The hosts come from CHAPELCORE_TLS_HOSTS (config/chapelcore.php). The core's
 * TlsCertificateProbe decides what the dates mean.
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Doctor\TlsCertificateExpiries;
use Tests\Support\LocalTlsServer;
use Tests\TestCase;

final class TlsCertificateExpiriesTest extends TestCase
{
    private ?LocalTlsServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        parent::tearDown();
    }

    public function test_it_reads_when_a_hosts_certificate_expires(): void
    {
        $server = $this->startServer(validDays: 45);

        $expiries = (new TlsCertificateExpiries(["127.0.0.1:{$server->port}"]))->expiries();

        $this->assertEquals(["127.0.0.1:{$server->port}" => $server->expiresAt], $expiries);
    }

    public function test_a_host_that_cannot_be_reached_has_no_date(): void
    {
        $port = self::closedPort();

        $expiries = (new TlsCertificateExpiries(["127.0.0.1:{$port}"], timeoutSeconds: 2))->expiries();

        $this->assertSame(["127.0.0.1:{$port}" => null], $expiries);
    }

    public function test_every_host_is_read_in_order_and_keyed_as_written(): void
    {
        $server = $this->startServer(validDays: 10);
        $closed = self::closedPort();
        $hosts = ["127.0.0.1:{$closed}", "127.0.0.1:{$server->port}"];

        $expiries = (new TlsCertificateExpiries($hosts, timeoutSeconds: 2))->expiries();

        $this->assertSame($hosts, array_keys($expiries));
        $this->assertNull($expiries[$hosts[0]]);
        $this->assertEquals($server->expiresAt, $expiries[$hosts[1]]);
    }

    public function test_no_hosts_means_no_dates(): void
    {
        $this->assertSame([], (new TlsCertificateExpiries([]))->expiries());
    }

    private function startServer(int $validDays): LocalTlsServer
    {
        return $this->server = new LocalTlsServer($validDays);
    }

    /** A port on 127.0.0.1 that nothing listens on: one that was free a moment ago. */
    private static function closedPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($address, strrpos($address, ':') + 1);
    }
}
