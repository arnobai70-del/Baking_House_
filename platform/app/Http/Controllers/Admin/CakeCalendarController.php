<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CakeBlackoutDate;
use App\Models\OrderItem;
use App\Models\PickupLocation;
use App\Services\AuditLogger;
use App\Services\Cakes\CakeSchedule;
use App\Services\Cakes\CakeSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Custom cakes, operations side: the production calendar (booked cakes per day against capacity, closed
 * dates, dates opened for urgent orders) and store pickup locations.
 */
class CakeCalendarController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function calendar(CakeSettings $settings): View
    {
        $today = CarbonImmutable::now(CakeSchedule::TIMEZONE)->startOfDay();
        $days = max(1, (int) $settings->get('cake.booking_window_days'));
        $booked = OrderItem::query()->where('type', 'cake')
            ->whereBetween('scheduled_for', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->whereHas('order', fn ($order) => $order->where('status', '!=', OrderStatus::Cancelled->value))
            ->selectRaw('date(scheduled_for) as day, count(*) as cakes')->groupBy('day')->pluck('cakes', 'day');
        $exceptions = CakeBlackoutDate::query()->where('date', '>=', $today->toDateString())->orderBy('date')->get();

        return view('admin.cakes.calendar', [
            'days' => collect(range(0, $days))->map(fn (int $offset) => $today->addDays($offset))->map(fn (CarbonImmutable $day) => [
                'date' => $day,
                'booked' => (int) ($booked[$day->toDateString()] ?? 0),
                'exception' => $exceptions->first(fn (CakeBlackoutDate $e) => $e->date->toDateString() === $day->toDateString()),
            ]),
            'capacity' => $settings->capacity(),
            'ordersOpen' => $settings->ordersOpen(),
            'exceptions' => $exceptions,
        ]);
    }

    public function storeDate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'kind' => ['required', Rule::in([CakeBlackoutDate::CLOSED, CakeBlackoutDate::URGENT_OPEN])],
            'reason' => ['nullable', 'string', 'max:160'],
        ]);
        $exception = CakeBlackoutDate::query()->updateOrCreate(['date' => $validated['date']], [
            'kind' => $validated['kind'], 'reason' => $validated['reason'] ?? null, 'created_by' => $request->user()?->id,
        ]);
        $this->audit->record('cake.date_exception_saved', $exception, [], $exception->only(['date', 'kind', 'reason']));

        return redirect()->route('admin.cakes.calendar')->with('status', $validated['kind'] === CakeBlackoutDate::CLOSED
            ? "{$validated['date']} closed for custom cakes. Existing orders on that date are not changed."
            : "{$validated['date']} opened for urgent orders (still within the daily capacity).");
    }

    public function destroyDate(CakeBlackoutDate $exception): RedirectResponse
    {
        $this->audit->record('cake.date_exception_removed', $exception, $exception->only(['date', 'kind', 'reason']));
        $exception->delete();

        return redirect()->route('admin.cakes.calendar')->with('status', 'Date returned to the normal rules.');
    }

    public function pickup(Request $request): View
    {
        return view('admin.cakes.pickup', [
            'locations' => PickupLocation::query()->orderBy('sort_order')->orderBy('name')->get(),
            'editing' => $request->integer('edit') ? PickupLocation::query()->find($request->integer('edit')) : null,
        ]);
    }

    public function storePickup(Request $request): RedirectResponse
    {
        $location = $this->savePickup($request, new PickupLocation);

        return redirect()->route('admin.cakes.pickup')->with('status', "Pickup location “{$location->name}” added.");
    }

    public function updatePickup(Request $request, PickupLocation $location): RedirectResponse
    {
        $this->savePickup($request, $location);

        return redirect()->route('admin.cakes.pickup')->with('status', "Pickup location “{$location->name}” saved.");
    }

    private function savePickup(Request $request, PickupLocation $location): PickupLocation
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'hours' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $before = $location->exists ? $location->only(['name', 'address', 'hours', 'is_active']) : [];
        $location->fill($validated + ['is_active' => $request->boolean('is_active'), 'sort_order' => (int) ($validated['sort_order'] ?? 0)])->save();
        $this->audit->record($before === [] ? 'cake.pickup_created' : 'cake.pickup_updated', $location, $before, $location->only(['name', 'address', 'hours', 'is_active']));

        return $location;
    }
}
