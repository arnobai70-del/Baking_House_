<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerAudience;
use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\AdminInput;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Combo / bundle packages of real SKUs. Quantities are grams for weight items, units otherwise.
 */
class BundleController extends Controller
{
    public function index(): View
    {
        return view('admin.bundles.index', ['bundles' => Bundle::query()->withCount('items')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.bundles.form', ['bundle' => new Bundle(['is_active' => true, 'discount_type' => DiscountType::Percentage, 'audiences' => ['retail']])]);
    }

    public function edit(Bundle $bundle): View
    {
        return view('admin.bundles.form', ['bundle' => $bundle->load('items.variant:id,sku')]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $bundle = $this->save($request, new Bundle, $audit);

        return redirect()->route('admin.bundles.edit', $bundle)->with('status', 'Bundle created.');
    }

    public function update(Request $request, Bundle $bundle, AuditLogger $audit): RedirectResponse
    {
        $this->save($request, $bundle, $audit);

        return back()->with('status', 'Bundle saved.');
    }

    private function save(Request $request, Bundle $bundle, AuditLogger $audit): Bundle
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'name_bn' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('bundles', 'slug')->ignore($bundle->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'string', 'max:20'],
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*' => [Rule::enum(CustomerAudience::class)],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'items' => ['array', 'max:30'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = DiscountType::from($validated['discount_type']);
        $value = $type === DiscountType::Percentage ? AdminInput::percentToBasisPoints($validated['discount_value']) : Taka::parse($validated['discount_value']);
        if ($value === null) {
            throw ValidationException::withMessages(['discount_value' => 'Enter a valid discount.']);
        }

        $items = [];
        $errors = [];
        foreach ($validated['items'] ?? [] as $index => $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }
            $variant = ProductVariant::query()->where('sku', $sku)->first();
            if ($variant === null || $variant->listPricePaisa() === null) {
                $errors["items.{$index}.sku"] = "{$sku} is unknown or quote-only.";
            } elseif (isset($items[$variant->id])) {
                $errors["items.{$index}.sku"] = "{$sku} is listed twice.";
            } else {
                $items[$variant->id] = ['product_variant_id' => $variant->id, 'quantity' => max(1, (int) ($row['quantity'] ?? 1))];
            }
        }
        if ($items === []) {
            $errors['items'] = 'Add at least one item.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $isNew = ! $bundle->exists;
        DB::transaction(function () use ($request, $bundle, $validated, $type, $value, $items): void {
            $bundle->fill([
                'name' => $validated['name'],
                'name_bn' => $validated['name_bn'] ?? null,
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'discount_type' => $type,
                'discount_value' => $value,
                'audiences' => array_values($validated['audiences']),
                'is_active' => $request->boolean('is_active'),
                'starts_at' => AdminInput::dhakaToUtc($validated['starts_at'] ?? null),
                'ends_at' => AdminInput::dhakaToUtc($validated['ends_at'] ?? null),
            ])->save();
            $bundle->items()->delete();
            foreach ($items as $item) {
                $bundle->items()->create($item);
            }
        });

        $audit->record($isNew ? 'bundle.created' : 'bundle.updated', $bundle, after: [
            'slug' => $bundle->slug, 'discount' => $type->describe($value), 'items' => count($items), 'active' => $bundle->is_active,
        ]);

        return $bundle;
    }
}
