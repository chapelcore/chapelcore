<?php

/**
 * CertificateExpiries.php — the port the tls.certificate check reads from.
 *
 * Why: the rule (30 and 7 days) is plain PHP, but which hosts to ask, and asking them
 * over the network, belongs to the adapter. A separate API host has its own
 * certificate, so the answer is a list.
 *
 * How it connects: Laravel (1.5) implements it for the site's and the API's hosts;
 * TlsCertificateProbe judges every answer.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Doctor;

use DateTimeImmutable;

interface CertificateExpiries
{
    /** @return array<string, ?DateTimeImmutable> when each host's certificate expires, by host; null if it couldn't be read */
    public function expiries(): array;
}
