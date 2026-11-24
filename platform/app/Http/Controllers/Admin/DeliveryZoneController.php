<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Services\AuditLogger;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Delivery zones (districts → charge). A district can belong to one zone; one zone may be the
 * default for unlisted districts. Charges are placeholders until courier rates are agreed.
 */
class DeliveryZoneController extends Controller
{
    public function index(): View
    {
        return view('admin.zones.index', ['zones' => DeliveryZone::query()->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.zones.form', ['zone' => new DeliveryZone(['is_active' => true, 'cod_available' => true, 'districts' => []]), 'districts' => DeliveryZone::DISTRICTS]);
    }

    public function edit(DeliveryZone $zone): View
    {
        return view('admin.zones.form', ['zone' => $zone, 'districts' => DeliveryZone::DISTRICTS]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $zone = $this->save($request, new DeliveryZone, $audit);

        return redirect()->route('admin.zones.index')->with('status', "Zone “{$zone->name}” created.");
    }

    public function update(Request $request, DeliveryZone $zone, AuditLogger $audit): RedirectResponse
    {
        $this->save($request, $zone, $audit);

        return redirect()->route('admin.zones.index')->with('status', "Zone “{$zone->name}” saved.");
    }

    private function save(Request $request, DeliveryZone $zone, AuditLogger $audit): DeliveryZone
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'districts' => ['array'],
            'districts.*' => [Rule::in(DeliveryZone::DISTRICTS)],
            'is_default' => ['boolean'],
            'charge' => ['required', 'string', 'max:20'],
            'free_over' => ['nullable', 'string', 'max:20'],
            'estimate' => ['nullable', 'string', 'max:120'],
            'cod_available' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'cake_delivery_enabled' => ['boolean'],
            'cake_charge' => ['nullable', 'string', 'max:20'],
            'cake_free_over' => ['nullable', 'string', 'max:20'],
            'cake_min_order' => ['nullable', 'string', 'max:20'],
            'cake_tiered_allowed' => ['boolean'],
        ]);

        $charge = Taka::parse($validated['charge']);
        if ($charge === null) {
            throw ValidationException::withMessages(['charge' => 'Enter the delivery charge in taka.']);
        }

        $districts = array_values(array_unique($validated['districts'] ?? []));
        $taken = DeliveryZone::query()->whereKeyNot($zone->id ?? 0)->get()->flatMap(fn (DeliveryZone $other) => array_intersect($districts, $other->districts ?? []))->unique();
        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages(['districts' => 'Already in another zone: '.$taken->implode(', ')]);
        }

        $before = $zone->exists ? $zone->only(['name', 'charge_paisa', 'districts', 'is_active', 'cake_delivery_enabled', 'cake_charge_paisa']) : [];

        DB::transaction(function () use ($request, $zone, $validated, $charge, $districts): void {
            if ($request->boolean('is_default')) {
                DeliveryZone::query()->whereKeyNot($zone->id ?? 0)->update(['is_default' => false]);
            }
            $zone->fill([
                'name' => $validated['name'],
                'name_bn' => $validated['name_bn'] ?? null,
                'districts' => $districts,
                'is_default' => $request->boolean('is_default'),
                'charge_paisa' => $charge,
                'free_over_paisa' => Taka::parse($validated['free_over'] ?? null),
                'estimate' => $validated['estimate'] ?? null,
                'cod_available' => $request->boolean('cod_available'),
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
                'cake_delivery_enabled' => $request->boolean('cake_delivery_enabled'),
                'cake_charge_paisa' => Taka::parse($validated['cake_charge'] ?? null),
                'cake_free_over_paisa' => Taka::parse($validated['cake_free_over'] ?? null),
                'cake_min_order_paisa' => Taka::parse($validated['cake_min_order'] ?? null),
                'cake_tiered_allowed' => $request->boolean('cake_tiered_allowed'),
            ])->save();
        });

        $audit->record($before === [] ? 'delivery_zone.created' : 'delivery_zone.updated', $zone, $before, $zone->only(['name', 'charge_paisa', 'districts', 'is_active', 'cake_delivery_enabled', 'cake_charge_paisa']));

        return $zone;
    }
}
