<?php

namespace App\Support;

/**
 * Resolve an ISO 3166-1 alpha-2 code to an English country name, and validate
 * one. A two-letter input that ICU doesn't recognise as a real region (a
 * mistyped "DU" for "AE", say) is treated as invalid rather than echoed back.
 */
class CountryName
{
    public static function resolve(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '' || ! class_exists(\Locale::class)) {
            return null;
        }

        $name = (string) \Locale::getDisplayRegion('-'.$code, 'en');

        return ($name !== '' && strtoupper($name) !== $code) ? $name : null;
    }

    public static function isValid(?string $code): bool
    {
        return self::resolve($code) !== null;
    }
}
