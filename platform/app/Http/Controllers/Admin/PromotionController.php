<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerAudience;
use App\Enums\DiscountType;
use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Services\AuditLogger;
use App\Support\AdminInput;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Coupons and automatic promotions. Only a super admin (prices.approve) may mark a promotion
 * stackable — i.e. authorise it to combine with other discounts or trade prices.
 */
class PromotionController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', ['promotions' => Promotion::query()->latest('id')->get()]);
    }

    public function create(): View
    {
        return $this->form(new Promotion(['is_active' => true, 'discount_type' => DiscountType::Percentage, 'scope' => Promotion::SCOPE_ORDER, 'audiences' => ['retail']]));
    }

    public function edit(Promotion $promotion): View
    {
        return $this->form($promotion->load('products.variants:id,product_id,sku'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $promotion = $this->save($request, new Promotion, $audit);

        return redirect()->route('admin.promotions.edit', $promotion)->with('status', 'Promotion created.');
    }

    public function update(Request $request, Promotion $promotion, AuditLogger $audit): RedirectResponse
    {
        $this->save($request, $promotion, $audit);

        return back()->with('status', 'Promotion saved.');
    }

    private function form(Promotion $promotion): View
    {
        return view('admin.promotions.form', [
            'promotion' => $promotion,
            'canStack' => auth()->user()->can(StaffPermission::ApprovePriceChanges->value),
        ]);
    }

    private function save(Request $request, Promotion $promotion, AuditLogger $audit): Promotion
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-_]+$/', Rule::unique('promotions', 'code')->ignore($promotion->id)],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'string', 'max:20'],
            'scope' => ['required', Rule::in([Promotion::SCOPE_ORDER, Promotion::SCOPE_PRODUCTS])],
            'product_skus' => ['nullable', 'string', 'max:2000'],
            'min_subtotal' => ['nullable', 'string', 'max:20'],
            'max_discount' => ['nullable', 'string', 'max:20'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => [Rule::enum(CustomerAudience::class)],
            'stackable' => ['boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $type = DiscountType::from($validated['discount_type']);
        $value = $type === DiscountType::Percentage ? AdminInput::percentToBasisPoints($validated['discount_value']) : Taka::parse($validated['discount_value']);
        if ($value === null || $value === 0) {
            throw ValidationException::withMessages(['discount_value' => $type === DiscountType::Percentage ? 'Enter a percentage between 0.01 and 100.' : 'Enter an amount in taka.']);
        }

        $productIds = [];
        if ($validated['scope'] === Promotion::SCOPE_PRODUCTS) {
            $skus = array_values(array_filter(array_map(fn ($sku) => strtoupper(trim($sku)), explode(',', (string) ($validated['product_skus'] ?? '')))));
            $variants = ProductVariant::query()->whereIn('sku', $skus)->get(['sku', 'product_id']);
            $unknown = array_diff($skus, $variants->pluck('sku')->all());
            if ($skus === [] || $unknown !== []) {
                throw ValidationException::withMessages(['product_skus' => $skus === [] ? 'List at least one SKU.' : 'Unknown SKU(s): '.implode(', ', $unknown)]);
            }
            $productIds = $variants->pluck('product_id')->unique()->values()->all();
        }

        $canStack = $request->user()->can(StaffPermission::ApprovePriceChanges->value);
        $before = $promotion->exists ? $this->snapshot($promotion) : [];

        DB::transaction(function () use ($request, $promotion, $validated, $type, $value, $productIds, $canStack): void {
            $promotion->fill([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'discount_type' => $type,
                'discount_value' => $value,
                'scope' => $validated['scope'],
                'min_subtotal_paisa' => Taka::parse($validated['min_subtotal'] ?? null) ?? 0,
                'max_discount_paisa' => Taka::parse($validated['max_discount'] ?? null),
                'audiences' => array_values($validated['audiences']),
                'stackable' => $canStack ? $request->boolean('stackable') : (bool) $promotion->stackable,
                'usage_limit' => $validated['usage_limit'] ?? null,
                'per_customer_limit' => $validated['per_customer_limit'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'starts_at' => AdminInput::dhakaToUtc($validated['starts_at'] ?? null),
                'ends_at' => AdminInput::dhakaToUtc($validated['ends_at'] ?? null),
            ])->save();
            $promotion->products()->sync($productIds);
        });

        $audit->record($before === [] ? 'promotion.created' : 'promotion.updated', $promotion, $before, $this->snapshot($promotion->fresh()));

        return $promotion;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Promotion $promotion): array
    {
        return [
            'coupon' => $promotion->code,
            'discount' => $promotion->describe(),
            'scope' => $promotion->scope,
            'audiences' => $promotion->audiences,
            'stackable' => $promotion->stackable,
            'active' => $promotion->is_active,
            'usage_limit' => $promotion->usage_limit,
        ];
    }
}
