<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Encrypts small secrets kept in the database (an integration's webhook
 * address, a two-step sign-in key) with APP_KEY from .env, using
 * AES-256-GCM, so a copy of the database alone doesn't give them away.
 * The key itself is never stored in the database or shown.
 */
final class Secrets
{
    private const PREFIX = 'v1:';

    private ?string $key;

    public function __construct(string $appKey)
    {
        $appKey = trim($appKey);
        if (str_starts_with($appKey, 'base64:')) {
            $raw = base64_decode(substr($appKey, 7), true);
            $this->key = is_string($raw) && strlen($raw) >= 32 ? hash('sha256', $raw, true) : null;
        } else {
            $this->key = strlen($appKey) >= 32 ? hash('sha256', $appKey, true) : null;
        }
    }

    /** A new key for APP_KEY (bin/app-key.php). */
    public static function newKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /** False when APP_KEY is missing or too short; nothing can be encrypted then. */
    public function available(): bool
    {
        return $this->key !== null && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
    }

    public function encrypt(string $plain): string
    {
        if (!$this->available()) {
            throw new RuntimeException('APP_KEY is not set in .env.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', (string) $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Null when it can't be read (another key, or changed). */
    public function decrypt(string $sealed): ?string
    {
        if (!$this->available() || !str_starts_with($sealed, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);
        if (!is_string($raw) || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', (string) $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? null : $plain;
    }
}
