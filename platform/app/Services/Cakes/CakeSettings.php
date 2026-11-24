<?php

namespace App\Services\Cakes;

use App\Models\Setting;

/**
 * Admin-managed custom-cake rules, stored in the existing settings table. Nothing here is a business fact:
 * defaults are the owner's stated defaults (48 h lead time, 50 % advance) or "closed / off" until set.
 */
final class CakeSettings
{
    public const DEFAULTS = [
        'cake.orders_open' => false,             // closed until the owner opens them (and sets a capacity)
        'cake.daily_capacity' => null,           // cakes per day; null = not configured → no dates offered
        'cake.lead_hours_standard' => 48,
        'cake.simple_enabled' => false,          // 24 h "simple" lead time only when switched on
        'cake.lead_hours_simple' => 24,
        'cake.lead_hours_complex' => 72,
        'cake.time_slots' => ['10:00-13:00', '14:00-17:00', '18:00-21:00'],
        'cake.advance_percent' => 50,
        'cake.advance_high_value_threshold_paisa' => null, // orders at or above this total …
        'cake.advance_high_value_percent' => null,         // … use this advance instead
        'cake.cod_enabled' => true,              // balance (or the whole order when no advance applies) payable on delivery
        'cake.rush_fee_paisa' => null,           // optional fee for dates inside the rush window
        'cake.rush_window_hours' => null,
        'cake.booking_window_days' => 30,        // how far ahead dates are offered
        'cake.cancellation_policy' => null,      // shown to customers when set
    ];

    public function get(string $key): mixed
    {
        return Setting::get($key, self::DEFAULTS[$key] ?? null);
    }

    public function ordersOpen(): bool
    {
        return (bool) $this->get('cake.orders_open') && $this->capacity() !== null;
    }

    public function capacity(): ?int
    {
        $value = $this->get('cake.daily_capacity');

        return $value === null || $value === '' ? null : max(0, (int) $value);
    }

    /**
     * Lead time in hours for a complexity, with an optional per-product standard override.
     */
    public function leadHours(string $complexity, ?int $productStandardHours = null): int
    {
        $standard = $productStandardHours ?? (int) $this->get('cake.lead_hours_standard');

        return match ($complexity) {
            'simple' => (bool) $this->get('cake.simple_enabled') ? min($standard, (int) $this->get('cake.lead_hours_simple')) : $standard,
            'complex' => max($standard, (int) $this->get('cake.lead_hours_complex')),
            default => $standard,
        };
    }

    /**
     * @return list<string>
     */
    public function timeSlots(): array
    {
        return array_values(array_filter(array_map(fn ($slot) => self::normaliseSlot((string) $slot), (array) $this->get('cake.time_slots'))));
    }

    /**
     * Slot labels are compared in one form: trimmed, with en / em dashes written as a plain hyphen.
     */
    public static function normaliseSlot(?string $slot): string
    {
        return trim(str_replace(["\u{2013}", "\u{2014}"], '-', (string) $slot));
    }

    /**
     * Advance percentage for an order total (0–100), before any per-product override.
     */
    public function advancePercentFor(int $totalPaisa): int
    {
        $threshold = $this->get('cake.advance_high_value_threshold_paisa');
        $high = $this->get('cake.advance_high_value_percent');
        if ($threshold !== null && $high !== null && $totalPaisa >= (int) $threshold) {
            return $this->clampPercent((int) $high);
        }

        return $this->clampPercent((int) $this->get('cake.advance_percent'));
    }

    public function codEnabled(): bool
    {
        return (bool) $this->get('cake.cod_enabled');
    }

    public function rushFeeFor(int $hoursUntilSlot): int
    {
        $fee = $this->get('cake.rush_fee_paisa');
        $window = $this->get('cake.rush_window_hours');

        return $fee !== null && $window !== null && $hoursUntilSlot < (int) $window ? (int) $fee : 0;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            if ($value === null || $value === '') {
                // "Not set": drop the row so the default (usually null = off / not configured) applies.
                Setting::query()->whereKey($key)->delete();
                Setting::flushMemo();

                continue;
            }
            Setting::put($key, $value);
        }
    }

    private function clampPercent(int $percent): int
    {
        return max(0, min(100, $percent));
    }
}
