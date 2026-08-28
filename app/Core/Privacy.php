<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Privacy
{
    public static function encrypt(array $payload): string
    {
        $cipher = 'aes-256-gcm';
        $key = hash('sha256', (string) Config::get('app.secret'), true);
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $tag = '';
        $plaintext = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encrypted = openssl_encrypt((string) $plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($encrypted === false) {
            throw new RuntimeException('Falha ao proteger dados pessoais.');
        }
        return base64_encode($iv . $tag . $encrypted);
    }

    public static function decrypt(string $encoded): array
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            return [];
        }
        $cipher = 'aes-256-gcm';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, 16);
        $encrypted = substr($raw, $ivLength + 16);
        $plain = openssl_decrypt($encrypted, $cipher, hash('sha256', (string) Config::get('app.secret'), true), OPENSSL_RAW_DATA, $iv, $tag);
        $decoded = $plain === false ? null : json_decode($plain, true);
        return is_array($decoded) ? $decoded : [];
    }
}
