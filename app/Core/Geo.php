<?php

declare(strict_types=1);

namespace App\Core;

final class Geo
{
    public static function countryCode(): string
    {
        return self::location()['country'];
    }

    public static function location(): array
    {
        $trusted = Security::isCloudflareProxy();
        $header = (string) Config::get('security.country_header', 'GEOIP_COUNTRY_CODE');
        // HTTP_* values are client-controlled unless received from a verified proxy.
        $country = $trusted ? ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '') : (str_starts_with($header, 'HTTP_') ? '' : ($_SERVER[$header] ?? ''));
        $country = strtoupper(trim((string) $country));
        $city = $trusted ? ($_SERVER['HTTP_CF_IPCITY'] ?? '') : ($_SERVER['GEOIP_CITY'] ?? $_SERVER['GEOIP_CITY_NAME'] ?? '');
        $region = $trusted ? ($_SERVER['HTTP_CF_REGION'] ?? '') : ($_SERVER['GEOIP_REGION_NAME'] ?? $_SERVER['GEOIP_REGION'] ?? '');
        return ['country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : 'XX', 'city' => mb_substr(trim((string) $city), 0, 150), 'region' => mb_substr(trim((string) $region), 0, 150)];
    }

    public static function isBrazil(): bool
    {
        $country = self::countryCode();
        return $country === 'BR' || ($country === 'XX' && (bool) Config::get('security.allow_unknown_country', false));
    }
}
