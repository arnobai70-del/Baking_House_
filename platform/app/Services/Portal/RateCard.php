<?php

namespace App\Services\Portal;

use App\Enums\CustomerAudience;
use App\Enums\PriceListAudience;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use App\Support\Taka;

/**
 * The buyer's own price list: every visible variant that has a contract (this organization only)
 * or business / reseller tier, with the public price beside it. Built from the same PriceResolver
 * used at checkout, so what the portal shows is what the cart charges. Never includes another
 * organization's contract prices or the other trade audience's tiers.
 */
class RateCard
{
    public function __construct(private PriceResolver $prices) {}

    /**
     * @return list<array{variant: ProductVariant, name: string, sku: string, unit: string, standard_paisa: int, tiers: list<array{min_quantity: int, rate_paisa: int, source: string}>}>
     */
    public function rows(PricingContext $context, ?string $search = null): array
    {
        if ($context->organization === null) {
            return [];
        }

        $tradeAudience = $context->audience === CustomerAudience::Reseller ? PriceListAudience::Reseller : PriceListAudience::Business;
        $organizationId = $context->organization->id;

        $variantIds = PriceListItem::query()
            ->whereHas('priceList', function ($list) use ($context, $tradeAudience, $organizationId): void {
                $list->effectiveAt($context->at)->where(function ($scope) use ($tradeAudience, $organizationId): void {
                    $scope->where(fn ($contract) => $contract->where('audience', PriceListAudience::Contract->value)->where('organization_id', $organizationId))
                        ->orWhere(fn ($trade) => $trade->where('audience', $tradeAudience->value)->whereNull('organization_id'));
                });
            })
            ->distinct()
            ->pluck('product_variant_id');

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->visible())
            ->when($search !== null && trim($search) !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes(trim((string) $search), '%_\\').'%';
                $query->where(fn ($match) => $match->where('sku', 'like', $term)->orWhereHas('product', fn ($product) => $product->where('name', 'like', $term)));
            })
            ->with('product:id,name,slug')
            ->orderBy('sku')
            ->get();

        $rows = [];
        foreach ($variants as $variant) {
            $tiers = array_values(array_filter(
                $this->prices->visibleTiers($variant, $context),
                fn (array $tier) => in_array($tier['source'], ['contract', 'business-tier', 'reseller-tier'], true),
            ));
            if ($tiers === []) {
                continue;
            }

            $rows[] = [
                'variant' => $variant,
                'name' => $variant->product->name,
                'sku' => $variant->sku,
                'unit' => $variant->isWeight() ? 'kg' : 'each',
                'standard_paisa' => (int) ($variant->isWeight() ? $variant->per_kg_paisa : $variant->price_paisa),
                'tiers' => $tiers,
            ];
        }

        return $rows;
    }

    /**
     * CSV rows (header first) for download. Weight tiers start at a gram quantity.
     *
     * @return list<list<string>>
     */
    public function csv(PricingContext $context): array
    {
        $lines = [['SKU', 'Product', 'Unit', 'Public price (BDT)', 'Your price (BDT)', 'From quantity', 'Source']];
        foreach ($this->rows($context) as $row) {
            foreach ($row['tiers'] as $tier) {
                $lines[] = [
                    $row['sku'],
                    $row['name'],
                    $row['unit'] === 'kg' ? 'per kg' : 'each',
                    Taka::toInput($row['standard_paisa']),
                    Taka::toInput($tier['rate_paisa']),
                    $row['unit'] === 'kg' ? $tier['min_quantity'].' g' : (string) $tier['min_quantity'],
                    $tier['source'],
                ];
            }
        }

        return $lines;
    }
}
