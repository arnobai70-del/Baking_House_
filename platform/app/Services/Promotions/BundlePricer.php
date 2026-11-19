<?php

namespace App\Services\Promotions;

use App\Enums\DiscountType;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use App\Support\Taka;

/**
 * Bundle price = sum of children at STANDARD price × bundle quantity, minus the bundle discount
 * once. Each child must be active, sellable and in stock for the full quantity.
 */
class BundlePricer
{
    public function __construct(private PriceResolver $prices) {}

    /**
     * @return array{ok: bool, error: ?string, normal_paisa: int, discount_paisa: int, line_paisa: int, items: list<array{variant_id: int, quantity: int}>}
     */
    public function quote(Bundle $bundle, int $quantity, PricingContext $context): array
    {
        $fail = fn (string $error) => ['ok' => false, 'error' => $error, 'normal_paisa' => 0, 'discount_paisa' => 0, 'line_paisa' => 0, 'items' => []];

        if ($quantity < 1) {
            return $fail('Quantity must be at least 1.');
        }
        if (! Bundle::query()->whereKey($bundle->id)->effectiveAt($context->at)->exists()) {
            return $fail('This bundle is not currently offered.');
        }
        if (! $bundle->allowsAudience($context->audience)) {
            return $fail('This bundle is not available for your account type.');
        }

        $bundle->loadMissing('items.variant');
        $normal = 0;
        $items = [];

        /** @var BundleItem $item */
        foreach ($bundle->items as $item) {
            $variant = $item->variant;
            $needed = $item->quantity * $quantity;

            if ($variant === null || ! $variant->is_active || $variant->listPricePaisa() === null) {
                return $fail('An item in this bundle is no longer available.');
            }
            if ($variant->availableQuantity() < $needed) {
                return $fail("Not enough stock of {$variant->sku} for this bundle quantity.");
            }

            $normal += $this->prices->standardLine($variant, $item->quantity) * $quantity;
            $items[] = ['variant_id' => $variant->id, 'quantity' => $needed];
        }

        if ($items === []) {
            return $fail('This bundle has no items.');
        }

        $discount = match ($bundle->discount_type) {
            DiscountType::Percentage => Taka::divideRoundHalfUp($normal * $bundle->discount_value, 10000),
            DiscountType::FixedAmount => min($bundle->discount_value * $quantity, $normal),
        };

        return ['ok' => true, 'error' => null, 'normal_paisa' => $normal, 'discount_paisa' => $discount, 'line_paisa' => $normal - $discount, 'items' => $items];
    }
}
