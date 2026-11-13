<?php

/**
 * LocalTlsServer.php — starts tls-server.php with a new self-signed certificate, and
 * stops it again.
 *
 * Why: TlsCertificateExpiriesTest needs a server whose certificate it controls (see
 * tls-server.php). The certificate is made here with PHP's openssl functions, valid for
 * the given number of days, so the test can compare the date the adapter reads with the
 * date the certificate really has.
 *
 * How it connects: used by tests/Feature/TlsCertificateExpiriesTest. Runs the server in a
 * separate PHP process (proc_open), because a test can't be the server and the client at
 * once. Test-only.
 */

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

final class LocalTlsServer
{
    /** @var resource */
    private $process;

    private string $pemFile;

    public readonly int $port;

    public readonly DateTimeImmutable $expiresAt;

    public function __construct(int $validDays)
    {
        [$pem, $this->expiresAt] = self::selfSignedCertificate($validDays);
        $this->pemFile = (string) tempnam(sys_get_temp_dir(), 'chapelcore-tls-');
        file_put_contents($this->pemFile, $pem);

        $process = proc_open(
            [PHP_BINARY, __DIR__.'/tls-server.php', $this->pemFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if ($process === false) {
            throw new RuntimeException('Could not start the TLS test server');
        }
        $this->process = $process;

        $port = trim((string) fgets($pipes[1]));
        if (! ctype_digit($port)) {
            $this->stop();
            throw new RuntimeException('The TLS test server did not start: '.stream_get_contents($pipes[2]));
        }
        $this->port = (int) $port;
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        if (is_file($this->pemFile)) {
            unlink($this->pemFile);
        }
    }

    /** @return array{string, DateTimeImmutable} certificate and key as one PEM, and when the certificate expires */
    private static function selfSignedCertificate(int $validDays): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false) {
            throw new RuntimeException('Could not make a test key: '.openssl_error_string());
        }
        $signingKey = $key; // openssl_csr_new takes the key by reference
        $request = openssl_csr_new(['commonName' => '127.0.0.1'], $signingKey);
        $certificate = $request instanceof OpenSSLCertificateSigningRequest
            ? openssl_csr_sign($request, null, $key, $validDays)
            : false;
        if ($certificate === false
            || ! openssl_x509_export($certificate, $certificatePem)
            || ! openssl_pkey_export($key, $keyPem)
            || ! is_string($certificatePem) || ! is_string($keyPem)) {
            throw new RuntimeException('Could not make a test certificate: '.openssl_error_string());
        }

        $parsed = openssl_x509_parse($certificate);
        $validTo = is_array($parsed) ? $parsed['validTo_time_t'] ?? null : null;
        if (! is_int($validTo)) {
            throw new RuntimeException('Could not read the test certificate');
        }

        return [$certificatePem.$keyPem, (new DateTimeImmutable)->setTimestamp($validTo)];
    }
}
