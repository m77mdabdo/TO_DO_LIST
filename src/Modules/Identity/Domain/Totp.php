<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Time-based one-time codes (RFC 6238 over RFC 4226): HMAC-SHA1, 30-second
 * steps, 6 digits, the format every authenticator app reads. The secret is
 * 20 random bytes written in Base32 (RFC 4648) for typing or an otpauth:// link.
 */
final class Totp
{
    public const STEP = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    /** The code for a moment; $offset moves by whole steps. */
    public static function code(string $secret, int $time, int $offset = 0): string
    {
        $counter = intdiv($time, self::STEP) + $offset;
        $hash = hash_hmac('sha1', pack('J', $counter), self::unbase32($secret), true);
        $at = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$at]) & 0x7f) << 24) | (ord($hash[$at + 1]) << 16) | (ord($hash[$at + 2]) << 8) | ord($hash[$at + 3]);

        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Accepts the current step and one on either side (clocks drift), and
     * returns the step it matched so a code can't be used twice. Null when wrong.
     */
    public static function verify(string $secret, string $code, int $time): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }
        foreach ([0, -1, 1] as $offset) {
            if (hash_equals(self::code($secret, $time, $offset), $code)) {
                return intdiv($time, self::STEP) + $offset;
            }
        }

        return null;
    }

    /** The link authenticator apps open (also what a QR code would hold). */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=' . self::DIGITS . '&period=' . self::STEP;
    }

    /** The secret in groups of four, easier to type. */
    public static function grouped(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function unbase32(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text) ?? '')) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr((int) bindec($chunk));
            }
        }

        return $out;
    }
}
