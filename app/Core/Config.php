<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $GLOBALS['app_config'] ?? [];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
