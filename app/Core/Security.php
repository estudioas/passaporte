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
        if (self::isCloudflareProxy()) {
            $candidate = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    // Official Cloudflare ranges; only trust visitor headers from these peers.
    public static function isCloudflareProxy(): bool
    {
        $ip = @inet_pton((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === false) { return false; }
        $ranges = ['173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22','141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20','197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13','104.24.0.0/14','172.64.0.0/13','131.0.72.0/22','2400:cb00::/32','2606:4700::/32','2803:f800::/32','2405:b500::/32','2405:8100::/32','2a06:98c0::/29','2c0f:f248::/32'];
        foreach ($ranges as $range) {
            [$network, $bits] = explode('/', $range);
            $packed = inet_pton($network);
            if (strlen($packed) !== strlen($ip)) { continue; }
            $bytes = intdiv((int) $bits, 8); $remainder = (int) $bits % 8;
            if (substr($ip, 0, $bytes) !== substr($packed, 0, $bytes)) { continue; }
            if ($remainder === 0 || ((ord($ip[$bytes]) ^ ord($packed[$bytes])) & (255 << (8 - $remainder))) === 0) { return true; }
        }
        return false;
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
            $_COOKIE[$name] = $value;
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
