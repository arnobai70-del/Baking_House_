<?php

namespace App\Services\Catalog;

use App\Enums\StaffPermission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists a product and its variants in one transaction.
 *
 * - Price changes on existing variants need the prices.manage permission and a written reason.
 * - New variants with prices also need prices.manage.
 * - Every create / update / price change is written to the audit log with before/after values.
 */
class ProductWriter
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  product attributes
     * @param  list<int>  $categoryIds
     * @param  list<array<string, mixed>>  $variants  normalised variant attributes (ProductRequest::variantAttributes)
     */
    public function save(Product $product, array $attributes, array $categoryIds, array $variants, ?string $priceReason, User $actor): Product
    {
        $priceChanges = $this->detectPriceChanges($product, $variants);
        $this->guardPriceChanges($priceChanges, $priceReason, $actor);

        return DB::transaction(function () use ($product, $attributes, $categoryIds, $variants, $priceChanges, $priceReason): Product {
            $isNew = ! $product->exists;
            $before = $isNew ? [] : $product->only(array_keys($attributes));

            $product->fill($attributes)->save();
            $changed = $isNew ? $product->only(['name', 'slug', 'status']) : array_intersect_key($product->getChanges(), $attributes);
            $product->categories()->sync(array_values(array_unique([(int) $attributes['primary_category_id'], ...$categoryIds])));

            foreach ($variants as $data) {
                $this->saveVariant($product, $data);
            }

            $product->refreshSummary();

            if ($isNew || $changed !== []) {
                $this->audit->record(
                    $isNew ? 'product.created' : 'product.updated',
                    $product,
                    $isNew ? [] : $this->normalise(array_intersect_key($before, $changed)),
                    $this->normalise($changed),
                );
            }

            foreach ($priceChanges as $change) {
                $this->audit->record(
                    $change['is_new'] ? 'product.price_set' : 'product.price_changed',
                    $product,
                    $change['is_new'] ? [] : $change['before'],
                    $change['after'],
                    $change['is_new'] ? null : $priceReason,
                );
            }

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveVariant(Product $product, array $data): void
    {
        $id = $data['id'] ?? null;
        unset($data['id']);

        $variant = $id === null ? new ProductVariant : $product->variants()->whereKey($id)->firstOrFail();
        if ($id !== null) {
            /* Stock on existing variants changes only through the inventory ledger. */
            unset($data['stock_quantity']);
        }
        $variant->fill($data);
        $variant->product()->associate($product);
        $variant->save();
    }

    /**
     * Compare submitted prices with stored prices for existing variants; new variants with a
     * price are also treated as price changes (they set a price for the first time).
     *
     * @param  list<array<string, mixed>>  $variants
     * @return list<array{is_new: bool, before: array<string, mixed>, after: array<string, mixed>}>
     */
    private function detectPriceChanges(Product $product, array $variants): array
    {
        $existing = $product->exists ? $product->variants()->get()->keyBy('id') : collect();
        $changes = [];

        foreach ($variants as $data) {
            $current = isset($data['id']) ? $existing->get($data['id']) : null;
            $before = [];
            $after = [];

            foreach (ProductVariant::PRICE_FIELDS as $field) {
                $old = $current?->{$field};
                $new = $data[$field] ?? null;
                if ($old !== $new && ! ($current === null && $new === null)) {
                    $before[$field] = $old;
                    $after[$field] = $new;
                }
            }

            if ($after !== []) {
                $changes[] = [
                    'is_new' => $current === null,
                    'before' => ['sku' => $data['sku']] + $before,
                    'after' => ['sku' => $data['sku']] + $after,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  list<array{is_new: bool, before: array<string, mixed>, after: array<string, mixed>}>  $changes
     *
     * @throws ValidationException
     */
    private function guardPriceChanges(array $changes, ?string $reason, User $actor): void
    {
        if ($changes === []) {
            return;
        }

        if (! $actor->can(StaffPermission::ManagePrices->value)) {
            throw ValidationException::withMessages(['price_change_reason' => 'You do not have permission to set or change prices.']);
        }

        $hasExistingPriceEdit = collect($changes)->contains(fn (array $change) => ! $change['is_new']);

        if ($hasExistingPriceEdit && mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['price_change_reason' => 'Explain why the price is changing (at least 5 characters). It is saved in the audit log.']);
        }
    }

    /**
     * Enums → values so audit JSON stays readable.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalise(array $values): array
    {
        return array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }
}
