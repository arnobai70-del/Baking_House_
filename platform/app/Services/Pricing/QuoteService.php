<?php

namespace App\Services\Pricing;

use App\Models\Bundle;
use App\Models\CakeDesign;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Services\Cakes\CakePricer;
use App\Services\Promotions\AppliedPromotion;
use App\Services\Promotions\BundlePricer;
use App\Services\Promotions\PromotionEngine;
use App\Services\Quotations\QuotationPricer;

/**
 * Prices a whole basket on the server: variant lines (quantities for the same SKU are merged so
 * volume tiers apply on the total), bundles, then promotions. Used by POST /api/v1/pricing/quote
 * now and by cart / checkout in Phase 5. Accepted quotations (Phase 6) are one line each at the quoted
 * total and, like trade prices, take no coupon unless a promotion is stackable. Custom cake designs are
 * one line each, priced by CakePricer (product promotions apply to the cake's product). Client-supplied
 * prices are never read.
 */
class QuoteService
{
    public function __construct(
        private PriceResolver $prices,
        private BundlePricer $bundles,
        private PromotionEngine $promotions,
        private QuotationPricer $quotations,
        private CakePricer $cakes,
    ) {}

    /**
     * @param  list<array{sku: string, quantity: int}>  $items
     * @param  list<array{slug: string, quantity: int}>  $bundleLines
     * @param  list<string>  $quotationNumbers
     * @param  list<int>  $cakeDesignIds
     * @return array<string, mixed>
     */
    public function quote(array $items, array $bundleLines, PricingContext $context, ?string $couponCode = null, array $quotationNumbers = [], array $cakeDesignIds = []): array
    {
        $merged = [];
        foreach ($items as $item) {
            $sku = strtoupper(trim((string) $item['sku']));
            $merged[$sku] = ($merged[$sku] ?? 0) + (int) $item['quantity'];
        }

        $variants = ProductVariant::query()
            ->whereIn('sku', array_keys($merged))
            ->whereHas('product', fn ($product) => $product->visible())
            ->with('product:id,name,slug')
            ->get()
            ->keyBy('sku');

        $lines = [];
        $promotionLines = [];
        $valid = true;

        foreach ($merged as $sku => $quantity) {
            $variant = $variants->get($sku);
            if ($variant === null) {
                $lines[] = ['type' => 'item', 'sku' => $sku, 'ok' => false, 'error' => 'This product is not available.'];
                $valid = false;

                continue;
            }

            $quote = $this->prices->quote($variant, $quantity, $context);
            $lines[] = [
                'type' => 'item',
                'sku' => $sku,
                'name' => $variant->product->name,
                'quantity_label' => PriceResolver::quantityLabel($variant, $quantity),
            ] + $quote->toArray();

            if ($quote->ok) {
                $promotionLines[] = ['key' => $sku, 'product_id' => $variant->product_id, 'line_paisa' => (int) $quote->linePaisa, 'trade_priced' => $quote->isTradePriced(), 'is_bundle' => false];
            } else {
                $valid = false;
            }
        }

        foreach ($bundleLines as $line) {
            $bundle = Bundle::query()->where('slug', $line['slug'])->first();
            $result = $bundle === null
                ? ['ok' => false, 'error' => 'This bundle is not available.']
                : $this->bundles->quote($bundle, (int) $line['quantity'], $context);

            $lines[] = ['type' => 'bundle', 'slug' => $line['slug'], 'name' => $bundle?->name, 'quantity' => (int) $line['quantity']] + $result;

            if ($result['ok']) {
                $promotionLines[] = ['key' => 'bundle:'.$line['slug'], 'product_id' => null, 'line_paisa' => $result['line_paisa'], 'trade_priced' => false, 'is_bundle' => true];
            } else {
                $valid = false;
            }
        }

        foreach (array_values(array_unique($quotationNumbers)) as $number) {
            $line = $this->quotations->line(Quotation::query()->where('number', $number)->first(), $number, $context);
            $lines[] = $line;

            if ($line['ok']) {
                $promotionLines[] = ['key' => 'quotation:'.$number, 'product_id' => null, 'line_paisa' => $line['line_paisa'], 'trade_priced' => true, 'is_bundle' => false];
            } else {
                $valid = false;
            }
        }

        $designs = $cakeDesignIds === [] ? collect() : CakeDesign::query()->whereIn('id', array_values(array_unique($cakeDesignIds)))->orderBy('id')->get();
        foreach ($designs as $design) {
            $line = $this->cakes->line($design);
            $lines[] = $line;

            if ($line['ok']) {
                $promotionLines[] = ['key' => 'cake:'.$design->uuid, 'product_id' => $line['product_id'], 'line_paisa' => (int) $line['line_paisa'], 'trade_priced' => false, 'is_bundle' => false];
            } else {
                $valid = false;
            }
        }

        $subtotal = array_sum(array_column($promotionLines, 'line_paisa'));
        $promotionResult = $this->promotions->evaluate($promotionLines, $context, $couponCode);

        return [
            'audience' => $context->audience->value,
            'lines' => $lines,
            'subtotal_paisa' => $subtotal,
            'promotions' => array_map(fn (AppliedPromotion $applied) => $applied->toArray(), $promotionResult->applied),
            'discount_paisa' => $promotionResult->discountPaisa,
            'total_paisa' => max(0, $subtotal - $promotionResult->discountPaisa),
            'coupon' => $promotionResult->couponCode === null ? null : [
                'code' => $promotionResult->couponCode,
                'applied' => $promotionResult->couponApplied(),
                'message' => $promotionResult->couponMessage,
            ],
            'valid' => $valid && $lines !== [],
            'priced_at' => $context->at->format(DATE_ATOM),
            'notice' => 'Shipping and taxes are calculated at checkout. Prices are re-checked when the order is placed.',
        ];
    }
}
