<?php

namespace App\Services\Quotations;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\Pricing\PricingContext;

/**
 * Prices an accepted quotation as ONE cart / order line at exactly the quoted total.
 *
 * The line is valid only while the quotation is accepted, not expired, belongs to the buyer's
 * current organization context, and every catalog line still has the quoted quantity in stock.
 * Quantities and prices come from the quote, never from the cart or the client.
 */
class QuotationPricer
{
    /**
     * @return array<string, mixed>
     */
    public function line(?Quotation $quotation, string $number, PricingContext $context): array
    {
        $base = ['type' => 'quotation', 'number' => $number, 'quantity' => 1, 'name' => __('Quotation :number', ['number' => $number])];

        if ($quotation === null || ! $quotation->canBeOrderedBy($context->organization)) {
            return $base + ['ok' => false, 'error' => $quotation !== null && ! $quotation->isCurrent()
                ? __('This quotation has expired. Please request a new quote.')
                : __('This quotation is not available for the account you are buying for.')];
        }

        $items = $quotation->items()->with('variant:id,sku,product_id,stock_quantity,reserved_quantity,is_active')->get();
        $components = [];
        foreach ($items as $item) {
            /** @var QuotationItem $item */
            if ($item->product_variant_id !== null) {
                $variant = $item->variant;
                if ($variant === null || ! $variant->is_active) {
                    return $base + ['ok' => false, 'error' => __(':item is no longer available. Please contact us about this quotation.', ['item' => $item->description])];
                }
                if ($variant->availableQuantity() < $item->quantity) {
                    return $base + ['ok' => false, 'error' => __('Not enough stock of :item for this quotation right now. Please contact us.', ['item' => $item->description])];
                }
            }

            $components[] = [
                'variant_id' => $item->product_variant_id,
                'sku' => $item->sku,
                'name' => $item->description,
                'quantity' => $item->quantity,
                'quantity_label' => $item->quantity_label,
                'line_paisa' => $item->line_paisa,
            ];
        }

        $total = array_sum(array_column($components, 'line_paisa'));

        return $base + [
            'ok' => $components !== [] && $total === $quotation->total_paisa,
            'error' => $components === [] || $total !== $quotation->total_paisa ? __('This quotation is incomplete. Please contact us.') : null,
            'quotation_id' => $quotation->id,
            'rate_source' => 'quotation',
            'line_paisa' => $total,
            'standard_line_paisa' => $total,
            'valid_until' => $quotation->valid_until?->toAtomString(),
            'items' => $components,
        ];
    }
}
