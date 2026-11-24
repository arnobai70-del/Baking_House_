<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Http\Controllers\Controller;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\Cakes\CakePricer;
use App\Services\Cakes\CakeSchedule;
use App\Services\Cakes\CakeSettings;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Custom cakes, admin side (Part 1): ordering rules (capacity, lead times, slots, advance, COD, rush fee),
 * priced options with TEST / approved status, per-product cake rules, and a price simulator that runs the
 * same server pricer checkout uses. Cakes are normal catalogue products; nothing here duplicates the catalogue,
 * cart, checkout or order systems.
 */
class CakeSetupController extends Controller
{
    public function __construct(private CakeSettings $settings, private AuditLogger $audit) {}

    public function settings(): View
    {
        return view('admin.cakes.settings', [
            'values' => collect(array_keys(CakeSettings::DEFAULTS))->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])->all(),
            'testOptions' => CakeOption::query()->where('is_active', true)->where('is_test', true)->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'orders_open' => ['boolean'],
            'daily_capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'lead_hours_standard' => ['required', 'integer', 'min:1', 'max:720'],
            'simple_enabled' => ['boolean'],
            'lead_hours_simple' => ['required', 'integer', 'min:1', 'max:720'],
            'lead_hours_complex' => ['required', 'integer', 'min:1', 'max:720'],
            'time_slots' => ['required', 'string', 'max:500'],
            'advance_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'advance_high_value_threshold' => ['nullable', 'string', 'max:20'],
            'advance_high_value_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'cod_enabled' => ['boolean'],
            'rush_fee' => ['nullable', 'string', 'max:20'],
            'rush_window_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'booking_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_policy' => ['nullable', 'string', 'max:1000'],
        ]);

        $slots = collect(preg_split('/\R/', $validated['time_slots']) ?: [])->map(fn ($slot) => CakeSettings::normaliseSlot($slot))->filter()->unique()->values();
        $badSlot = $slots->first(fn (string $slot) => preg_match('/^\d{1,2}:\d{2}\s*-\s*\d{1,2}:\d{2}$/', $slot) !== 1);
        if ($slots->isEmpty() || $badSlot !== null) {
            throw ValidationException::withMessages(['time_slots' => 'One slot per line, like 10:00-13:00.'.($badSlot ? " Not understood: {$badSlot}" : '')]);
        }
        $money = fn (string $field): ?int => ($validated[$field] ?? '') === '' ? null
            : (Taka::parse($validated[$field]) ?? throw ValidationException::withMessages([$field => 'Enter an amount in taka.']));

        $values = [
            'cake.orders_open' => $request->boolean('orders_open'),
            'cake.daily_capacity' => $validated['daily_capacity'] ?? null,
            'cake.lead_hours_standard' => (int) $validated['lead_hours_standard'],
            'cake.simple_enabled' => $request->boolean('simple_enabled'),
            'cake.lead_hours_simple' => (int) $validated['lead_hours_simple'],
            'cake.lead_hours_complex' => (int) $validated['lead_hours_complex'],
            'cake.time_slots' => $slots->all(),
            'cake.advance_percent' => (int) $validated['advance_percent'],
            'cake.advance_high_value_threshold_paisa' => $money('advance_high_value_threshold'),
            'cake.advance_high_value_percent' => $validated['advance_high_value_percent'] ?? null,
            'cake.cod_enabled' => $request->boolean('cod_enabled'),
            'cake.rush_fee_paisa' => $money('rush_fee'),
            'cake.rush_window_hours' => $validated['rush_window_hours'] ?? null,
            'cake.booking_window_days' => (int) $validated['booking_window_days'],
            'cake.cancellation_policy' => trim((string) ($validated['cancellation_policy'] ?? '')) ?: null,
        ];
        $before = collect(array_keys($values))->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)])->all();
        $this->settings->save($values);
        $this->audit->record('cake.settings_updated', null, $before, $values);

        return redirect()->route('admin.cakes.settings')->with('status', 'Custom cake settings saved.');
    }

    public function options(Request $request): View
    {
        $editing = $request->integer('edit') ? CakeOption::query()->find($request->integer('edit')) : null;

        return view('admin.cakes.options', [
            'options' => CakeOption::query()->with('cakeProduct.product')->orderBy('group')->orderBy('sort_order')->orderBy('id')->get()->groupBy('group'),
            'editing' => $editing ?? new CakeOption(['group' => (string) $request->query('group', 'flavour'), 'price_type' => 'flat', 'complexity' => 'standard', 'is_active' => true, 'is_test' => true]),
            'cakes' => CakeProduct::query()->with('product')->get(),
        ]);
    }

    public function storeOption(Request $request): RedirectResponse
    {
        $option = $this->saveOption($request, new CakeOption);

        return redirect()->route('admin.cakes.options')->with('status', "Option “{$option->name}” added as a TEST price. Approve it before launch.");
    }

    public function updateOption(Request $request, CakeOption $option): RedirectResponse
    {
        $this->saveOption($request, $option);

        return redirect()->route('admin.cakes.options')->with('status', "Option “{$option->name}” saved.".($option->is_test ? ' Its price is TEST until approved.' : ''));
    }

    /**
     * Mark TEST prices as approved (owner / super admin only). Approved prices are what live checkout accepts.
     */
    public function approve(Request $request): RedirectResponse
    {
        Gate::authorize('prices.approve');
        $ids = array_map('intval', (array) $request->input('options', []));
        $query = CakeOption::query()->where('is_test', true);
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        $approved = $query->get();
        foreach ($approved as $option) {
            $option->forceFill(['is_test' => false])->save();
        }
        $this->audit->record('cake.prices_approved', null, [], ['options' => $approved->map(fn (CakeOption $o) => ['id' => $o->id, 'code' => $o->group.'.'.$o->code, 'price_paisa' => $o->price_paisa])->all()]);

        return redirect()->route('admin.cakes.options')->with('status', $approved->count().' cake price(s) approved.');
    }

    public function products(): View
    {
        $cakes = CakeProduct::query()->with('product.variants')->orderBy('id')->get();

        return view('admin.cakes.products', [
            'cakes' => $cakes,
            'candidates' => Product::query()->whereNotIn('id', $cakes->pluck('product_id'))
                ->whereHas('variants', fn ($variant) => $variant->where('pricing_mode', PricingMode::QuoteOnly->value)->where('is_active', true))
                ->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $validated = $request->validate(['product_id' => ['required', 'integer', Rule::exists('products', 'id'), Rule::unique('cake_products', 'product_id')]]);
        $product = Product::query()->findOrFail($validated['product_id']);
        if (! $product->variants()->where('is_active', true)->where('pricing_mode', PricingMode::QuoteOnly->value)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Give the product an active “quote only” variant first, so it can only be bought through the cake designer.']);
        }
        $cake = CakeProduct::query()->create(['product_id' => $product->id, 'min_grams' => 1000, 'max_grams' => 3000, 'step_grams' => 500, 'max_tiers' => 1]);
        $this->audit->record('cake.product_enabled', $cake, [], ['product_id' => $product->id]);

        return redirect()->route('admin.cakes.products.edit', $cake)->with('status', "“{$product->name}” can now be designed. Set its rules below.");
    }

    public function editProduct(CakeProduct $cake): View
    {
        return view('admin.cakes.product-form', ['cake' => $cake->load('product')]);
    }

    public function updateProduct(Request $request, CakeProduct $cake): RedirectResponse
    {
        $validated = $request->validate([
            'lead_time_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'advance_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'full_payment_required' => ['boolean'],
            'min_grams' => ['required', 'integer', 'min:250', 'max:20000'],
            'max_grams' => ['required', 'integer', 'gte:min_grams', 'max:20000'],
            'step_grams' => ['required', 'integer', 'min:100', 'max:5000'],
            'max_tiers' => ['required', 'integer', 'min:1', 'max:3'],
            'allow_eggless' => ['boolean'],
            'allow_sugar_free' => ['boolean'],
            'allow_both_diets' => ['boolean'],
            'ingredients' => ['nullable', 'string', 'max:2000'],
            'allergens' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $cake->only(array_keys($validated));
        $cake->fill($validated + [
            'full_payment_required' => $request->boolean('full_payment_required'),
            'allow_eggless' => $request->boolean('allow_eggless'),
            'allow_sugar_free' => $request->boolean('allow_sugar_free'),
            'allow_both_diets' => $request->boolean('allow_both_diets'),
        ])->save();
        $this->audit->record('cake.product_updated', $cake, $before, $cake->only(array_keys($validated)));

        return redirect()->route('admin.cakes.products')->with('status', "Cake rules for “{$cake->product->name}” saved.");
    }

    public function simulator(Request $request, CakePricer $pricer, CakeSchedule $schedule): View
    {
        $cakes = CakeProduct::query()->with('product')->orderBy('id')->get();
        $cake = $cakes->firstWhere('id', $request->integer('cake')) ?? $cakes->first();
        $spec = [
            'shape' => (string) $request->query('shape', ''), 'grams' => $request->integer('grams', $cake?->min_grams ?? 1000),
            'tiers' => max(1, min(3, $request->integer('tiers', 1))), 'flavour' => (string) $request->query('flavour', ''),
            'filling' => (string) $request->query('filling', ''), 'cream' => (string) $request->query('cream', ''),
            'diets' => array_values(array_intersect((array) $request->query('diets', []), ['eggless', 'sugar_free'])),
            'message' => $request->boolean('message') ? 'Message' : '', 'print' => $request->boolean('print'),
            'extras' => array_values(array_map('strval', (array) $request->query('extras', []))), 'note' => '',
        ];
        $priced = $cake && $request->has('cake')
            ? $pricer->price($cake->product->variants()->where('is_active', true)->orderBy('id')->with('product.cakeProfile')->first(), $spec)
            : null;

        return view('admin.cakes.simulator', [
            'cakes' => $cakes,
            'cake' => $cake,
            'spec' => $spec,
            'options' => $cake ? $pricer->optionsFor($cake)->groupBy('group') : collect(),
            'priced' => $priced,
            'advancePercent' => $priced ? max($this->settings->advancePercentFor($priced['line_paisa']), $cake->full_payment_required ? 100 : (int) $cake->advance_percent) : null,
            'leadHours' => $priced ? $this->settings->leadHours($priced['complexity'], $cake->lead_time_hours) : null,
            'dates' => $priced ? $schedule->availableDates($cake, $priced['complexity']) : [],
        ]);
    }

    /**
     * Create / update an option. Any new option or price change is a TEST price until approved again.
     */
    private function saveOption(Request $request, CakeOption $option): CakeOption
    {
        $validated = $request->validate([
            'group' => ['required', Rule::in(array_keys(CakeOption::GROUPS))],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'price_type' => ['required', Rule::in(array_keys(CakeOption::PRICE_TYPES))],
            'price' => ['required', 'string', 'max:20'],
            'complexity' => ['required', Rule::in(array_keys(CakeOption::COMPLEXITIES))],
            'requires_quote' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'cake_product_id' => ['nullable', 'integer', Rule::exists('cake_products', 'id')],
        ]);
        $price = Taka::parse($validated['price']);
        if ($price === null || $price < 0) {
            throw ValidationException::withMessages(['price' => 'Enter the price in taka (0 for included).']);
        }
        $productId = $validated['cake_product_id'] ?? null;
        $duplicate = CakeOption::query()->whereKeyNot($option->id ?? 0)->where('group', $validated['group'])->where('code', $validated['code'])
            ->when($productId === null, fn ($q) => $q->whereNull('cake_product_id'), fn ($q) => $q->where('cake_product_id', $productId))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['code' => 'This group already has an option with that code.']);
        }

        $before = $option->exists ? $option->only(['group', 'code', 'name', 'price_type', 'price_paisa', 'is_test', 'is_active', 'requires_quote', 'complexity']) : [];
        $priceChanged = ! $option->exists || $option->price_paisa !== $price || $option->price_type !== $validated['price_type'];
        $option->fill([
            'group' => $validated['group'], 'code' => $validated['code'], 'name' => $validated['name'], 'name_bn' => $validated['name_bn'] ?? null,
            'price_type' => $validated['price_type'], 'price_paisa' => $price, 'complexity' => $validated['complexity'],
            'requires_quote' => $request->boolean('requires_quote'), 'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0), 'cake_product_id' => $productId,
        ]);
        if ($priceChanged) {
            $option->is_test = true;
        }
        $option->save();
        $this->audit->record($before === [] ? 'cake.option_created' : 'cake.option_updated', $option, $before,
            $option->only(['group', 'code', 'name', 'price_type', 'price_paisa', 'is_test', 'is_active', 'requires_quote', 'complexity']));

        return $option;
    }
}
