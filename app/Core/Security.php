<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    public static function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function secretHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) Config::get('app.secret'));
    }

    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if ((bool) Config::get('security.trust_proxy_headers', false)) {
            $candidate = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function ipHash(): string
    {
        return self::secretHash(self::clientIp());
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public static function userAgentHash(): string
    {
        return self::secretHash(self::userAgent());
    }

    public static function deviceId(): string
    {
        $name = 'pr_device';
        $value = (string) ($_COOKIE[$name] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            $value = bin2hex(random_bytes(32));
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((bool) Config::get('security.trust_proxy_headers', false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            setcookie($name, $value, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        return $value;
    }

    public static function deviceHash(): string
    {
        return self::secretHash(self::deviceId());
    }

    public static function visitorHash(): string
    {
        return self::secretHash(self::deviceId() . '|' . self::ipHash() . '|' . self::userAgentHash());
    }

    public static function randomCode(int $bytes = 16): string
    {
        return strtoupper(bin2hex(random_bytes($bytes)));
    }

    public static function isSuspiciousUserAgent(): bool
    {
        $ua = strtolower(self::userAgent());
        if ($ua === '') {
            return true;
        }
        foreach (['bot', 'crawler', 'spider', 'headless', 'phantom', 'selenium', 'curl/', 'wget/'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }
}
