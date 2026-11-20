<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Enums\SellingUnit;
use App\Enums\StaffPermission;
use App\Support\Taka;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Product + variants form. Prices arrive as taka text and are converted to integer paisa;
 * weight rules are integer grams and must line up with the step (docs/pricing-rules.md).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(StaffPermission::ManageCatalog->value);
    }

    protected function prepareForValidation(): void
    {
        $variants = collect((array) $this->input('variants', []))
            ->filter(fn ($variant) => is_array($variant) && trim((string) ($variant['sku'] ?? '')) !== '')
            ->map(fn (array $variant) => [
                ...$variant,
                'sku' => Str::upper(trim((string) $variant['sku'])),
                'is_active' => filter_var($variant['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'preset_grams' => $this->parsePresets($variant['preset_grams'] ?? ''),
            ])
            ->values()
            ->all();

        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'is_featured' => $this->boolean('is_featured'),
            'category_ids' => array_values(array_filter((array) $this->input('category_ids', []))),
            'variants' => $variants,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:220', Rule::unique('products', 'slug')->ignore($this->route('product')?->id)],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['boolean'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_bn' => ['nullable', 'string', 'max:500'],
            'description_bn' => ['nullable', 'string', 'max:10000'],
            'description' => ['nullable', 'string', 'max:10000'],
            'specifications_text' => ['nullable', 'string', 'max:5000'],
            'search_keywords' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'price_change_reason' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'min:1', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'variants.*.selling_unit' => ['required', Rule::enum(SellingUnit::class)],
            'variants.*.pack_size' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.min_order_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.price' => ['nullable', 'string', 'max:20'],
            'variants.*.compare_at' => ['nullable', 'string', 'max:20'],
            'variants.*.per_kg' => ['nullable', 'string', 'max:20'],
            'variants.*.min_grams' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'variants.*.max_grams' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'variants.*.step_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'variants.*.preset_grams' => ['array', 'max:10'],
            'variants.*.preset_grams.*' => ['integer', 'min:1'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],
        ];

        /* SKU rules are per row (not wildcard) so each row can ignore its own id in the unique check. */
        foreach ((array) $this->input('variants', []) as $index => $variant) {
            $rules["variants.{$index}.sku"] = [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9\-_.]*$/',
                Rule::unique('product_variants', 'sku')->ignore($variant['id'] ?? null),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.sku.unique' => 'SKU :input is already used by another product.',
            'variants.*.sku.regex' => 'SKUs use capital letters, digits, dashes, dots or underscores.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $seen = [];
                foreach ((array) $this->input('variants', []) as $index => $variant) {
                    $this->validateVariant($validator, (int) $index, $variant);

                    $sku = (string) ($variant['sku'] ?? '');
                    if (isset($seen[$sku])) {
                        $validator->errors()->add("variants.{$seen[$sku]}.sku", 'Each variant needs its own SKU.');
                        $validator->errors()->add("variants.{$index}.sku", 'Each variant needs its own SKU.');
                    }
                    $seen[$sku] ??= $index;
                }

                $productId = $this->route('product')?->id;
                foreach ((array) $this->input('variants', []) as $index => $variant) {
                    if (! empty($variant['id']) && ($productId === null || ! $this->route('product')->variants()->whereKey($variant['id'])->exists())) {
                        $validator->errors()->add("variants.{$index}.id", 'This variant does not belong to the product.');
                    }
                }
            },
        ];
    }

    /**
     * Normalised variant attributes ready to persist (paisa, nulls for unused fields).
     *
     * @return list<array<string, mixed>>
     */
    public function variantAttributes(): array
    {
        return array_map(function (array $variant): array {
            $mode = PricingMode::from($variant['pricing_mode']);
            $unit = $mode === PricingMode::Weight ? SellingUnit::Gram : SellingUnit::from($variant['selling_unit']);
            $isWeight = $mode === PricingMode::Weight;

            return [
                'id' => $variant['id'] ?? null,
                'sku' => $variant['sku'],
                'name' => $variant['name'],
                'pricing_mode' => $mode,
                'selling_unit' => $unit,
                'pack_size' => $unit->hasPackSize() ? ($variant['pack_size'] ?? null) : null,
                'min_order_quantity' => $isWeight ? 1 : max(1, (int) ($variant['min_order_quantity'] ?? 1)),
                'price_paisa' => $mode === PricingMode::Fixed ? Taka::parse($variant['price'] ?? null) : null,
                'compare_at_paisa' => $mode === PricingMode::Fixed ? Taka::parse($variant['compare_at'] ?? null) : null,
                'per_kg_paisa' => $isWeight ? Taka::parse($variant['per_kg'] ?? null) : null,
                'min_grams' => $isWeight ? (int) $variant['min_grams'] : null,
                'max_grams' => $isWeight ? (int) $variant['max_grams'] : null,
                'step_grams' => $isWeight ? (int) $variant['step_grams'] : null,
                'preset_grams' => $isWeight ? array_values($variant['preset_grams'] ?? []) : null,
                'stock_quantity' => (int) $variant['stock_quantity'],
                'low_stock_threshold' => (int) ($variant['low_stock_threshold'] ?? 0),
                'is_active' => (bool) $variant['is_active'],
            ];
        }, $this->validated('variants'));
    }

    /**
     * "Key: Value" lines → ['Key' => 'Value'].
     *
     * @return array<string, string>
     */
    public function specifications(): array
    {
        $specifications = [];
        foreach (preg_split('/\R/', (string) $this->validated('specifications_text')) ?: [] as $line) {
            [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
            if (trim($key) !== '' && trim($value) !== '') {
                $specifications[trim($key)] = trim($value);
            }
        }

        return $specifications;
    }

    /**
     * @param  array<string, mixed>  $variant
     */
    private function validateVariant(Validator $validator, int $index, array $variant): void
    {
        $mode = PricingMode::tryFrom((string) ($variant['pricing_mode'] ?? ''));
        $prefix = "variants.{$index}";

        if ($mode === PricingMode::Fixed) {
            if (Taka::parse($variant['price'] ?? null) === null) {
                $validator->errors()->add("{$prefix}.price", 'Enter a price in taka, e.g. 650 or 812.50.');
            }
            if (($variant['compare_at'] ?? '') !== '' && Taka::parse($variant['compare_at']) === null) {
                $validator->errors()->add("{$prefix}.compare_at", 'Enter the compare-at price in taka.');
            }
            $unit = SellingUnit::tryFrom((string) ($variant['selling_unit'] ?? ''));
            if ($unit === SellingUnit::Gram) {
                $validator->errors()->add("{$prefix}.selling_unit", 'Gram items must use weight pricing.');
            }
            if ($unit?->hasPackSize() && empty($variant['pack_size'])) {
                $validator->errors()->add("{$prefix}.pack_size", 'Enter how many units are in the pack.');
            }
        }

        if ($mode !== PricingMode::Weight) {
            return;
        }

        if (Taka::parse($variant['per_kg'] ?? null) === null) {
            $validator->errors()->add("{$prefix}.per_kg", 'Enter the price per kg in taka.');
        }

        $min = (int) ($variant['min_grams'] ?? 0);
        $max = (int) ($variant['max_grams'] ?? 0);
        $step = (int) ($variant['step_grams'] ?? 0);

        if ($min < 1 || $max < 1 || $step < 1) {
            $validator->errors()->add("{$prefix}.step_grams", 'Weight items need minimum, maximum and step in grams.');

            return;
        }
        if ($min % $step !== 0 || $max % $step !== 0) {
            $validator->errors()->add("{$prefix}.step_grams", 'Minimum and maximum must be multiples of the step.');
        }
        if ($max < $min) {
            $validator->errors()->add("{$prefix}.max_grams", 'Maximum must be at least the minimum.');
        }
        foreach ($variant['preset_grams'] ?? [] as $preset) {
            if ($preset < $min || $preset > $max || $preset % $step !== 0) {
                $validator->errors()->add("{$prefix}.preset_grams", "Preset {$preset} g must be within min–max and a multiple of the step.");
                break;
            }
        }
    }

    /**
     * "100, 250, 500" → [100, 250, 500]
     *
     * @return list<int>
     */
    private function parsePresets(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }

        return array_values(array_map('intval', array_filter(preg_split('/[\s,]+/', (string) $value) ?: [], 'is_numeric')));
    }
}
