<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Conversions for admin form input. Staff type taka, percentages and Bangladesh local times;
 * the database stores paisa, basis points and UTC.
 */
class AdminInput
{
    public const TIMEZONE = 'Asia/Dhaka';

    /**
     * "12.5" → 1250 basis points. Null when invalid or above 100%.
     */
    public static function percentToBasisPoints(?string $input): ?int
    {
        $text = trim((string) $input);
        if (preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $text, $matches) !== 1) {
            return null;
        }

        $basisPoints = (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');

        return $basisPoints >= 1 && $basisPoints <= 10000 ? $basisPoints : null;
    }

    public static function basisPointsToInput(?int $basisPoints): string
    {
        if ($basisPoints === null) {
            return '';
        }

        return rtrim(rtrim(number_format($basisPoints / 100, 2, '.', ''), '0'), '.');
    }

    /**
     * A datetime-local value in Dhaka time → UTC, or null when empty.
     */
    public static function dhakaToUtc(?string $input): ?CarbonImmutable
    {
        return $input === null || trim($input) === '' ? null : CarbonImmutable::parse($input, self::TIMEZONE)->utc();
    }

    /**
     * UTC → datetime-local value in Dhaka time.
     */
    public static function utcToDhakaInput(?\DateTimeInterface $value): string
    {
        return $value === null ? '' : CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i');
    }
}
