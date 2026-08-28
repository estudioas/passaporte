<?php

declare(strict_types=1);

namespace App\Core;

final class Geo
{
    public static function countryCode(): string
    {
        $header = (string) Config::get('security.country_header', 'HTTP_CF_IPCOUNTRY');
        $value = strtoupper(trim((string) ($_SERVER[$header] ?? '')));
        return preg_match('/^[A-Z]{2}$/', $value) ? $value : 'XX';
    }

    public static function isBrazil(): bool
    {
        $country = self::countryCode();
        return $country === 'BR' || ($country === 'XX' && (bool) Config::get('security.allow_unknown_country', false));
    }
}
