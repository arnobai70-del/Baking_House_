<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerAudience;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use App\Support\AdminInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "What would this buyer pay?" — runs the real PriceResolver for any SKU, quantity, customer
 * type, organization and date, so staff can preview list and schedule changes before they go live.
 */
class PriceSimulatorController extends Controller
{
    public function __invoke(Request $request, PriceResolver $prices): View
    {
        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:64'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'audience' => ['nullable', Rule::enum(CustomerAudience::class)],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'at' => ['nullable', 'date'],
        ]);

        $variant = filled($validated['sku'] ?? null) ? ProductVariant::query()->where('sku', strtoupper(trim($validated['sku'])))->with('product:id,name')->first() : null;
        $organization = isset($validated['organization_id']) ? Organization::query()->find($validated['organization_id']) : null;
        $at = AdminInput::dhakaToUtc($validated['at'] ?? null);

        $context = $organization !== null
            ? PricingContext::forOrganization($organization, at: $at)
            : new PricingContext(CustomerAudience::tryFrom($validated['audience'] ?? '') ?? CustomerAudience::Retail, null, null, $at);

        $quote = $variant !== null && isset($validated['quantity']) ? $prices->quote($variant, (int) $validated['quantity'], $context, PHP_INT_MAX) : null;

        return view('admin.pricing.simulator', [
            'input' => $validated,
            'variant' => $variant,
            'context' => $context,
            'quote' => $quote,
            'tiers' => $variant !== null ? $prices->visibleTiers($variant, $context) : [],
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name', 'type', 'status']),
            'skuMissing' => filled($validated['sku'] ?? null) && $variant === null,
        ]);
    }
}
