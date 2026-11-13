<?php

/**
 * TlsCertificateExpiries.php — connects to each host and reads when its TLS certificate
 * expires, for the doctor's tls.certificate check.
 *
 * Why: a missed acme.sh renewal takes the site down (phase-1 R10), so the doctor warns
 * 30 days ahead. It only reads the date, so it reads it even from a certificate a browser
 * wouldn't trust: "expired on …" says more than "could not connect". A host it can't
 * reach gets null.
 *
 * How it connects: the adapter for the core's CertificateExpiries port, bound in
 * AppServiceProvider with the hosts from CHAPELCORE_TLS_HOSTS (config/chapelcore.php);
 * the core's TlsCertificateProbe decides what the dates mean.
 */

declare(strict_types=1);

namespace App\Doctor;

use ChapelCore\Core\Doctor\CertificateExpiries;
use DateTimeImmutable;
use OpenSSLCertificate;

final readonly class TlsCertificateExpiries implements CertificateExpiries
{
    /** @param list<string> $hosts as host or host:port */
    public function __construct(
        private array $hosts,
        private float $timeoutSeconds = 5.0,
    ) {}

    /** @return array<string, ?DateTimeImmutable> */
    public function expiries(): array
    {
        $expiries = [];
        foreach ($this->hosts as $host) {
            $expiries[$host] = $this->expiry($host);
        }

        return $expiries;
    }

    /** When $host's certificate expires, or null when it can't be read. */
    private function expiry(string $host): ?DateTimeImmutable
    {
        // "example.org" or "example.org:8443": parse_url needs a scheme to split the two
        $name = parse_url("tls://{$host}", PHP_URL_HOST);
        $port = parse_url("tls://{$host}", PHP_URL_PORT) ?? 443;
        if (! is_string($name)) {
            return null;
        }

        // only the date is read and nothing is sent, so the certificate needn't be trusted;
        // the host name still goes along (SNI), so a shared server answers with the right one
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);
        // @: a host that can't be reached is an answer (null), not a PHP warning
        $connection = @stream_socket_client("ssl://{$name}:{$port}", $errorCode, $errorMessage, $this->timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
        if ($connection === false) {
            return null;
        }
        $ssl = stream_context_get_options($connection)['ssl'] ?? null;
        $certificate = is_array($ssl) ? $ssl['peer_certificate'] ?? null : null;
        fclose($connection);
        if (! $certificate instanceof OpenSSLCertificate) {
            return null;
        }

        $parsed = openssl_x509_parse($certificate);
        $validTo = is_array($parsed) ? $parsed['validTo_time_t'] ?? null : null;

        return is_int($validTo) ? (new DateTimeImmutable)->setTimestamp($validTo) : null;
    }
}
