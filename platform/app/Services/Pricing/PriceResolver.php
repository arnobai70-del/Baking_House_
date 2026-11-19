<?php

namespace App\Services\Pricing;

use App\Enums\CustomerAudience;
use App\Enums\PriceListAudience;
use App\Enums\PricingMode;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use App\Support\Taka;
use App\Support\Weight;
use Illuminate\Support\Collection;

/**
 * Server-authoritative pricing for a variant + quantity + context (blueprint §05–06).
 *
 * Precedence (first match wins, matching the tested JS/PHP weight engine):
 *   contract list of the buyer's organization → business / reseller list → campaign list →
 *   retail volume list → variant standard price.
 * Within one list the tier with the highest min_quantity not above the requested quantity wins
 * (volume thresholds use TOTAL grams / units, not cart rows).
 *
 * Line maths: weight → round_half_up(grams × per_kg_paisa / 1000); fixed → unit_paisa × qty. No floats.
 */
class PriceResolver
{
    public function __construct(private WeightPricing $weightPricing) {}

    public function quote(ProductVariant $variant, int $quantity, PricingContext $context, ?int $availableQuantity = null): PriceQuote
    {
        $rejection = $this->validate($variant, $quantity, $availableQuantity ?? $variant->availableQuantity());
        if ($rejection !== null) {
            return $rejection;
        }

        [$rate, $source, $listId] = $this->resolveRate($variant, $quantity, $context);
        $standardRate = $this->standardRate($variant);

        return PriceQuote::priced(
            $variant->id,
            $quantity,
            $variant->isWeight() ? null : $rate,
            $variant->isWeight() ? $rate : null,
            $source,
            $listId,
            $this->line($variant, $quantity, $rate),
            $this->line($variant, $quantity, $standardRate),
        );
    }

    /**
     * Standard (undiscounted) line price — used for bundles, which never take trade tiers.
     */
    public function standardLine(ProductVariant $variant, int $quantity): int
    {
        return $this->line($variant, $quantity, $this->standardRate($variant));
    }

    /**
     * Tier table for the buyer's highest-precedence list that prices this variant, for display
     * (only ever the caller's own tiers).
     *
     * @return list<array{min_quantity: int, rate_paisa: int, source: string}>
     */
    public function visibleTiers(ProductVariant $variant, PricingContext $context): array
    {
        $field = $variant->isWeight() ? 'per_kg_paisa' : 'price_paisa';

        foreach ($this->audiencesFor($context) as $audience) {
            $items = $this->items($variant, $context, $audience)->filter(fn (PriceListItem $item) => $item->{$field} !== null);
            if ($items->isNotEmpty()) {
                return $items->sortBy('min_quantity')->map(fn (PriceListItem $item) => [
                    'min_quantity' => $item->min_quantity,
                    'rate_paisa' => $item->{$field},
                    'source' => $audience->source(),
                ])->values()->all();
            }
        }

        return [];
    }

    private function validate(ProductVariant $variant, int $quantity, int $available): ?PriceQuote
    {
        if (! $variant->is_active || $variant->pricing_mode === PricingMode::QuoteOnly) {
            return PriceQuote::rejected($variant->id, $quantity, 'quote_only', 'This item is available by quotation only.');
        }

        if ($variant->isWeight()) {
            $weightQuote = $this->weightPricing->validate($variant->weightRule(), $quantity, $available);

            return $weightQuote === null ? null : PriceQuote::rejected($variant->id, $quantity, (string) $weightQuote->errorCode, (string) $weightQuote->error, $weightQuote->suggestions);
        }

        $minimum = $variant->minimumQuantity();
        if ($quantity < $minimum) {
            return PriceQuote::rejected($variant->id, $quantity, 'below_min', "Minimum order is {$minimum}.", [$minimum]);
        }
        if ($quantity > $available) {
            return PriceQuote::rejected($variant->id, $quantity, 'stock', $available > 0 ? "Only {$available} available." : 'Out of stock.', $available >= $minimum ? [$available] : []);
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string, 2: int|null}
     */
    private function resolveRate(ProductVariant $variant, int $quantity, PricingContext $context): array
    {
        $field = $variant->isWeight() ? 'per_kg_paisa' : 'price_paisa';

        foreach ($this->audiencesFor($context) as $audience) {
            $tier = $this->items($variant, $context, $audience)
                ->filter(fn (PriceListItem $item) => $item->{$field} !== null && $item->min_quantity <= $quantity)
                ->sortByDesc('min_quantity')
                ->first();

            if ($tier !== null) {
                return [(int) $tier->{$field}, $audience->source(), $tier->price_list_id];
            }
        }

        return [$this->standardRate($variant), 'standard', null];
    }

    /**
     * @return list<PriceListAudience>
     */
    private function audiencesFor(PricingContext $context): array
    {
        $audiences = [];
        if ($context->organization !== null) {
            $audiences[] = PriceListAudience::Contract;
        }
        if ($context->audience === CustomerAudience::Business) {
            $audiences[] = PriceListAudience::Business;
        }
        if ($context->audience === CustomerAudience::Reseller) {
            $audiences[] = PriceListAudience::Reseller;
        }
        $audiences[] = PriceListAudience::Campaign;
        $audiences[] = PriceListAudience::Retail;

        return $audiences;
    }

    /**
     * Items of effective lists of one audience for this variant. Contract lists are restricted
     * to the buyer's own organization.
     *
     * @return Collection<int, PriceListItem>
     */
    private function items(ProductVariant $variant, PricingContext $context, PriceListAudience $audience): Collection
    {
        return PriceListItem::query()
            ->where('product_variant_id', $variant->id)
            ->whereHas('priceList', function ($list) use ($audience, $context): void {
                $list->effectiveAt($context->at)->where('audience', $audience->value);
                if ($audience === PriceListAudience::Contract) {
                    $list->where('organization_id', $context->organization?->id);
                } else {
                    $list->whereNull('organization_id');
                }
            })
            ->get();
    }

    private function standardRate(ProductVariant $variant): int
    {
        return (int) ($variant->isWeight() ? $variant->per_kg_paisa : $variant->price_paisa);
    }

    private function line(ProductVariant $variant, int $quantity, int $rate): int
    {
        return $variant->isWeight() ? Taka::divideRoundHalfUp($quantity * $rate, 1000) : $rate * $quantity;
    }

    /**
     * Human label for a quantity in base units.
     */
    public static function quantityLabel(ProductVariant $variant, int $quantity): string
    {
        return $variant->isWeight() ? Weight::format($quantity) : "× {$quantity}";
    }
}
