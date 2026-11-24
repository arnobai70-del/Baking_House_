<?php

namespace App\Services\Cakes;

use App\Enums\OrderStatus;
use App\Models\CakeBlackoutDate;
use App\Models\CakeProduct;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Which dates and time slots a custom cake can be delivered / collected, in Dhaka time.
 *
 * A date is offered only when: cake orders are open and a daily capacity is set; it lies inside the booking
 * window; it is not a closed (holiday) date; the slot starts after the lead time for the design's complexity
 * (or the admin opened the date for urgent orders); and fewer cakes than the daily capacity are already booked
 * for it by placed, not-cancelled orders.
 */
class CakeSchedule
{
    public const TIMEZONE = 'Asia/Dhaka';

    public function __construct(private CakeSettings $settings) {}

    /**
     * @return list<array{date: string, remaining: int, slots: list<string>, urgent: bool}>
     */
    public function availableDates(CakeProduct $cake, string $complexity): array
    {
        if (! $this->settings->ordersOpen()) {
            return [];
        }

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $days = max(1, (int) $this->settings->get('cake.booking_window_days'));
        $dates = [];
        for ($i = 0; $i <= $days; $i++) {
            $date = $today->addDays($i)->format('Y-m-d');
            $slots = array_values(array_filter($this->settings->timeSlots(), fn (string $slot) => $this->check($cake, $complexity, $date, $slot) === null));
            if ($slots !== []) {
                $dates[] = ['date' => $date, 'remaining' => $this->remaining($date), 'slots' => $slots, 'urgent' => $this->exception($date) === CakeBlackoutDate::URGENT_OPEN];
            }
        }

        return $dates;
    }

    /**
     * Why this date / slot cannot be chosen (null when it can).
     */
    public function check(?CakeProduct $cake, string $complexity, string $date, ?string $slot): ?string
    {
        if ($cake === null || ! $this->settings->ordersOpen()) {
            return __('Custom cake orders are closed right now.');
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE);
        if ($day === false) {
            return __('Choose a valid date.');
        }
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        if ($day->lt($today) || $day->gt($today->addDays(max(1, (int) $this->settings->get('cake.booking_window_days'))))) {
            return __('That date is outside the booking window.');
        }

        $exception = $this->exception($date);
        if ($exception === CakeBlackoutDate::CLOSED) {
            return __('We are not baking on that date.');
        }

        $slot = $slot === null ? null : CakeSettings::normaliseSlot($slot);
        if ($slot === null || ! in_array($slot, $this->settings->timeSlots(), true)) {
            return __('Choose a time slot.');
        }

        $start = $this->slotStart($date, $slot);
        $lead = $this->settings->leadHours($complexity, $cake->lead_time_hours);
        $earliest = CarbonImmutable::now(self::TIMEZONE)->addHours($lead);
        if ($exception === CakeBlackoutDate::URGENT_OPEN) {
            if ($start->lte(CarbonImmutable::now(self::TIMEZONE))) {
                return __('That time slot has already started.');
            }
        } elseif ($start->lt($earliest)) {
            return __('This cake needs at least :hours hours to prepare. Choose a later date or slot.', ['hours' => $lead]);
        }

        if ($this->remaining($date) <= 0) {
            return __('That date is fully booked. Choose another date.');
        }

        return null;
    }

    /**
     * Cakes still bookable on a date: capacity minus cakes on placed, not-cancelled orders.
     */
    public function remaining(string $date): int
    {
        $capacity = $this->settings->capacity() ?? 0;
        $booked = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.type', 'cake')
            ->where('order_items.scheduled_for', $date)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->count();

        return max(0, $capacity - $booked);
    }

    /**
     * Hours from now until the slot starts (used for the optional rush fee).
     */
    public function hoursUntil(string $date, ?string $slot): int
    {
        $start = $slot === null ? CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE) : $this->slotStart($date, $slot);

        return $start === false ? PHP_INT_MAX : (int) floor(CarbonImmutable::now(self::TIMEZONE)->diffInMinutes($start, false) / 60);
    }

    private function exception(string $date): ?string
    {
        return CakeBlackoutDate::query()->whereDate('date', $date)->value('kind');
    }

    /**
     * Start of a slot such as "10:00–13:00" on a date, in Dhaka time.
     */
    private function slotStart(string $date, string $slot): CarbonImmutable
    {
        preg_match('/(\d{1,2}):(\d{2})/', $slot, $match);

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE)->setTime((int) ($match[1] ?? 0), (int) ($match[2] ?? 0));
    }
}
