<?php

/**
 * tls-server.php — a tiny TLS server for tests: it answers handshakes with one
 * certificate until it is stopped.
 *
 * Why: the doctor reads certificate expiry dates over the network, and a test can't rely
 * on a real site's certificate (its date changes, and CI may have no internet). This
 * serves a certificate the test made, so the test knows the exact date to expect.
 *
 * How it connects: started and stopped by Tests\Support\LocalTlsServer, which passes the
 * certificate file (certificate and key in one PEM) as the only argument. It listens on a
 * free port on 127.0.0.1 and prints that port on its first line. Test-only; nothing in
 * the app runs it.
 */

declare(strict_types=1);

$pem = $argv[1] ?? '';
if (! is_file($pem)) {
    fwrite(STDERR, "usage: php tls-server.php <certificate-and-key.pem>\n");
    exit(1);
}

$context = stream_context_create(['ssl' => ['local_cert' => $pem]]);
$server = stream_socket_server('tls://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, "could not listen: {$errorMessage}\n");
    exit(1);
}

$address = (string) stream_socket_get_name($server, false);
echo substr($address, strrpos($address, ':') + 1), "\n";

// plenty for any test; the test stops the server long before
for ($served = 0; $served < 1000; $served++) {
    // the handshake happens inside accept; the client only reads the certificate
    $client = @stream_socket_accept($server, -1);
    if ($client !== false) {
        fclose($client);
    }
}
