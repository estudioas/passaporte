<?php

declare(strict_types=1);

namespace App\Core;

final class Turnstile
{
    public static function siteKey(): string
    {
        return trim((string) Settings::get('turnstile_site_key', ''));
    }

    private static function secretKey(): string
    {
        $stored = trim((string) Settings::get('turnstile_secret_encrypted', ''));
        if ($stored === '') {
            return '';
        }
        return trim((string) (Privacy::decrypt($stored)['secret'] ?? ''));
    }

    public static function configured(): bool
    {
        return self::siteKey() !== '' && self::secretKey() !== '';
    }

    public static function verify(?string $token, string $expectedAction = 'vote'): bool
    {
        $token = trim((string) $token);
        $secret = self::secretKey();
        if ($token === '' || $secret === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => Security::clientIp(),
        ]);
        $body = false;
        if (function_exists('curl_init')) {
            $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $body = curl_exec($curl);
            curl_close($curl);
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 8,
            ]]);
            $body = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        }
        if (!is_string($body) || $body === '') {
            return false;
        }
        $result = json_decode($body, true);
        if (!is_array($result) || empty($result['success'])) {
            return false;
        }
        $hostname = strtolower(trim((string) ($result['hostname'] ?? '')));
        $expectedHost = strtolower((string) parse_url((string) Config::get('app.base_url'), PHP_URL_HOST));
        if ($hostname !== '' && $expectedHost !== '' && !hash_equals($expectedHost, $hostname)) {
            return false;
        }
        $action = trim((string) ($result['action'] ?? ''));
        return $action === '' || hash_equals($expectedAction, $action);
    }
}
