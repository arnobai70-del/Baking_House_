<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\ScheduledPriceChange;
use App\Services\AuditLogger;
use App\Support\AdminInput;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Schedule a future standard-price change (applied by `pricing:apply-scheduled`) or cancel one.
 */
class ScheduledPriceChangeController extends Controller
{
    public function index(): View
    {
        return view('admin.pricing.scheduled', [
            'changes' => ScheduledPriceChange::query()->with(['variant:id,sku,product_id', 'variant.product:id,name', 'creator:id,name'])
                ->orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('effective_at')->limit(200)->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64'],
            'field' => ['required', Rule::in(ScheduledPriceChange::FIELDS)],
            'new_price' => ['nullable', 'string', 'max:20'],
            'effective_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $variant = ProductVariant::query()->where('sku', strtoupper(trim($validated['sku'])))->first();
        $effectiveAt = AdminInput::dhakaToUtc($validated['effective_at']);
        $newValue = Taka::parse($validated['new_price'] ?? null);

        $errors = [];
        if ($variant === null) {
            $errors['sku'] = 'Unknown SKU.';
        } elseif ($validated['field'] === 'per_kg_paisa' && ! $variant->isWeight()) {
            $errors['field'] = 'Per-kg price applies to weight items only.';
        } elseif ($validated['field'] === 'price_paisa' && $variant->isWeight()) {
            $errors['field'] = 'Weight items use the per-kg price.';
        }
        if ($newValue === null && $validated['field'] !== 'compare_at_paisa') {
            $errors['new_price'] = 'Enter the new price in taka.';
        }
        if ($effectiveAt !== null && $effectiveAt->isPast()) {
            $errors['effective_at'] = 'Choose a time in the future.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $change = ScheduledPriceChange::query()->create([
            'product_variant_id' => $variant->id,
            'field' => $validated['field'],
            'new_value_paisa' => $newValue,
            'effective_at' => $effectiveAt,
            'reason' => $validated['reason'],
            'created_by' => $request->user()->id,
        ]);

        $audit->record('price_change.scheduled', $change, after: [
            'sku' => $variant->sku, 'field' => $change->field, 'new_value_paisa' => $newValue, 'effective_at' => $effectiveAt->toIso8601String(),
        ], reason: $validated['reason']);

        return back()->with('status', "Price change for {$variant->sku} scheduled.");
    }

    public function destroy(Request $request, ScheduledPriceChange $scheduledPriceChange, AuditLogger $audit): RedirectResponse
    {
        $cancelled = ScheduledPriceChange::query()->whereKey($scheduledPriceChange->id)
            ->where('status', ScheduledPriceChange::STATUS_PENDING)
            ->update(['status' => ScheduledPriceChange::STATUS_CANCELLED, 'updated_at' => now()]);

        abort_if($cancelled !== 1, 409, 'Only pending changes can be cancelled.');
        $audit->record('price_change.cancelled', $scheduledPriceChange);

        return back()->with('status', 'Scheduled change cancelled.');
    }
}
