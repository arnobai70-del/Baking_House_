<?php

namespace App\Support;

/**
 * Bangladesh mobile numbers: accepts 01XXXXXXXXX, +8801XXXXXXXXX or 8801XXXXXXXXX
 * (spaces / dashes allowed) and normalises to the 11-digit local form.
 */
class BangladeshPhone
{
    private const PATTERN = '/^(?:\+?88)?(01[3-9]\d{8})$/';

    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-()]/', '', $input);

        return preg_match(self::PATTERN, (string) $digits, $matches) === 1 ? $matches[1] : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    public static function looksLikePhone(string $identifier): bool
    {
        return ! str_contains($identifier, '@');
    }
}
