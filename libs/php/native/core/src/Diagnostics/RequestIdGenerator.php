<?php

/**
 * RequestIdGenerator.php — makes a new reference code for each request.
 *
 * Why: the code ties together what a person sees, the X-Request-Id header and the log
 * (DOMAIN-MODEL §2, RefCode). It is 5 random bytes (40 bits) written as 8 Crockford
 * Base32 characters: about a trillion possible codes, with no letters that are easy to
 * misread (I, L, O, U).
 *
 * How it connects: returns a RefCode; Laravel (1.5) calls generate() once per request.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Diagnostics;

use ChapelCore\Core\Model\RefCode;
use Closure;
use LogicException;

final readonly class RequestIdGenerator
{
    /** Crockford Base32: digits and letters except I, L, O and U. */
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int BYTES = 5;

    /** @var Closure(int<1, max>): string */
    private Closure $randomBytes;

    /**
     * @param (Closure(int<1, max>): string)|null $randomBytes where the bytes come from;
     *        PHP's secure random_bytes() unless a test supplies known bytes
     */
    public function __construct(?Closure $randomBytes = null)
    {
        $this->randomBytes = $randomBytes ?? random_bytes(...);
    }

    /** @throws LogicException when the byte source returns the wrong number of bytes */
    public function generate(): RefCode
    {
        $bytes = ($this->randomBytes)(self::BYTES);
        if (strlen($bytes) !== self::BYTES) {
            throw new LogicException('The random byte source must return exactly ' . self::BYTES . ' bytes');
        }

        // 5 bytes make one 40-bit number; read it 5 bits at a time, most significant first.
        $number = 0;
        foreach (str_split($bytes) as $byte) {
            $number = ($number << 8) | ord($byte);
        }
        $code = '';
        for ($shift = 35; $shift >= 0; $shift -= 5) {
            $code .= self::ALPHABET[($number >> $shift) & 0b11111];
        }

        return new RefCode(substr($code, 0, 4) . '-' . substr($code, 4));
    }
}
