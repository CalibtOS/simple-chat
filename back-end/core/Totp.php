<?php
declare(strict_types=1);

/**
 * Pure PHP TOTP implementation (RFC 6238 / RFC 4226).
 *
 * How TOTP works — the short version:
 *
 *   1. A shared secret (random bytes, base32-encoded) is stored on both the
 *      server and in the user's authenticator app.
 *
 *   2. Both sides independently compute:
 *        T = floor( currentUnixTime / 30 )     ← the current 30-second window
 *        HMAC-SHA1( secret, T as 8-byte big-endian )
 *        Dynamic truncation → 6-digit code
 *
 *   3. They compare their codes.  Because both use the same secret and the
 *      same time window they produce the same number — without any network call.
 *
 *   We also accept T-1 and T+1 (one window either side) to tolerate small
 *   clock drift between the server and the user's phone.
 */
final class Totp
{
    private const DIGITS    = 6;
    private const PERIOD    = 30;    // seconds per window
    private const DRIFT     = 1;     // windows of tolerance either side

    // ─── Public API ──────────────────────────────────────────────────────────────

    /**
     * Generate a new random TOTP secret (base32-encoded, 32 chars).
     * Store this in users.two_factor_secret.
     */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Build the otpauth:// URL that authenticator apps understand.
     * The user can scan this as a QR code or enter it manually.
     */
    public static function otpauthUrl(string $secret, string $email, string $issuer = 'Chatty'): string
    {
        return 'otpauth://totp/'
            . rawurlencode($issuer) . ':' . rawurlencode($email)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * Verify a user-submitted 6-digit code against the stored secret.
     * Returns true if valid (within the clock-drift window).
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $T = (int) floor(time() / self::PERIOD);

        for ($i = -self::DRIFT; $i <= self::DRIFT; $i++) {
            if (self::generateCode($secret, $T + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────────

    /**
     * Compute the HOTP code for a given counter value.
     *
     * Steps:
     *  1. Pack the counter as an 8-byte big-endian integer.
     *  2. HMAC-SHA1 with the base32-decoded secret.
     *  3. Dynamic truncation: use the last nibble as a byte offset,
     *     extract 4 bytes at that offset, mask the top bit, mod 10^6.
     */
    private static function generateCode(string $secret, int $counter): string
    {
        // Pack counter as 8-byte big-endian (PHP has no 64-bit pack on 32-bit
        // systems, so we split into two 32-bit halves).
        $high = (int) ($counter >> 32);
        $low  = $counter & 0xFFFFFFFF;
        $msg  = pack('NN', $high, $low);

        $hash   = hash_hmac('sha1', $msg, self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;

        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            |  (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encode (RFC 4648, no padding).
     * Used to turn random bytes into a human-copyable secret string.
     */
    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result   = '';
        $bits     = 0;
        $buffer   = 0;

        for ($i = 0, $len = strlen($bytes); $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits  += 8;
            while ($bits >= 5) {
                $bits   -= 5;
                $result .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        return $result;
    }

    /**
     * Base32 decode — turns the stored secret back into raw bytes for HMAC.
     */
    private static function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded  = strtoupper($encoded);
        $result   = '';
        $bits     = 0;
        $buffer   = 0;

        for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
            $val = strpos($alphabet, $encoded[$i]);
            if ($val === false) continue;  // skip padding / unknown chars
            $buffer = ($buffer << 5) | $val;
            $bits  += 5;
            if ($bits >= 8) {
                $bits   -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $result;
    }
}
