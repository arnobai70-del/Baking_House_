<?php

namespace App\Services\Promotions;

use App\Enums\DiscountType;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Services\Pricing\PricingContext;
use App\Support\Taka;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies coupons and automatic promotions under explicit combination rules (blueprint §06):
 *
 * 1. Eligibility: active, inside schedule, customer type allowed, usage / per-customer limits,
 *    minimum eligible subtotal.
 * 2. Eligible lines: standard / retail-tier / campaign-priced product lines. Lines on contract,
 *    business or reseller prices, and bundle lines, are excluded unless the promotion is
 *    explicitly `stackable` (owner-authorised combination).
 * 3. At most ONE non-stackable promotion applies — the one worth most to the customer.
 *    Stackable promotions add on top. Only one coupon code per order.
 * 4. Total discount never exceeds the eligible subtotal; totals never go negative.
 */
class PromotionEngine
{
    /**
     * @param  list<array{key: string, product_id: int|null, line_paisa: int, trade_priced: bool, is_bundle: bool}>  $lines
     */
    public function evaluate(array $lines, PricingContext $context, ?string $couponCode = null): PromotionResult
    {
        $couponCode = $couponCode === null || trim($couponCode) === '' ? null : strtoupper(trim($couponCode));
        $candidates = Promotion::query()->effectiveAt($context->at)->whereNull('code')->with('products:id')->get();
        $couponMessage = null;
        $coupon = null;

        if ($couponCode !== null) {
            $coupon = Promotion::query()->where('code', $couponCode)->with('products:id')->first();
            $couponMessage = $this->couponProblem($coupon, $context);
            if ($couponMessage === null) {
                $candidates->push($coupon);
            } else {
                $coupon = null;
            }
        }

        $evaluated = [];
        foreach ($candidates as $promotion) {
            if (! $promotion->allowsAudience($context->audience) || $promotion->isExhausted() || $this->customerLimitReached($promotion, $context)) {
                continue;
            }

            $base = $this->eligibleBase($promotion, $lines);
            if ($base <= 0 || $base < $promotion->min_subtotal_paisa) {
                if ($coupon !== null && $promotion->is($coupon)) {
                    $couponMessage = $base <= 0
                        ? 'This coupon does not apply to the items in your cart.'
                        : 'Spend at least '.Taka::format($promotion->min_subtotal_paisa).' on eligible items to use this coupon.';
                }

                continue;
            }

            $evaluated[] = ['promotion' => $promotion, 'discount' => $this->discountFor($promotion, $base), 'base' => $base];
        }

        $nonStackable = array_values(array_filter($evaluated, fn (array $entry) => ! $entry['promotion']->stackable));
        usort($nonStackable, fn (array $a, array $b) => $b['discount'] <=> $a['discount'] ?: $a['promotion']->id <=> $b['promotion']->id);
        $applied = array_values(array_filter($evaluated, fn (array $entry) => $entry['promotion']->stackable));
        if ($nonStackable !== []) {
            array_unshift($applied, $nonStackable[0]);
        }

        if ($coupon !== null && $couponMessage === null && ! collect($applied)->contains(fn (array $entry) => $entry['promotion']->is($coupon))) {
            $couponMessage = 'A better offer is already applied; this coupon cannot be combined with it.';
        }

        $cap = $this->eligibleBase(null, $lines, includeTradeAndBundles: collect($applied)->contains(fn (array $e) => $e['promotion']->stackable));
        $total = 0;
        $result = [];
        foreach ($applied as $entry) {
            $discount = min($entry['discount'], max(0, $cap - $total));
            if ($discount <= 0) {
                continue;
            }
            $total += $discount;
            $result[] = new AppliedPromotion($entry['promotion'], $discount);
        }

        return new PromotionResult($result, $total, $couponCode, $couponMessage);
    }

    /**
     * Record a redemption for an order reference. Atomic: respects usage_limit under concurrency and
     * never double-counts the same reference. Called at order placement (Phase 5).
     *
     * @throws RuntimeException when the promotion is exhausted or the customer limit is reached
     */
    public function redeem(Promotion $promotion, string $reference, int $discountPaisa, ?int $userId): PromotionRedemption
    {
        return DB::transaction(function () use ($promotion, $reference, $discountPaisa, $userId): PromotionRedemption {
            $existing = PromotionRedemption::query()->where('promotion_id', $promotion->id)->where('reference', $reference)->first();
            if ($existing !== null) {
                return $existing;
            }

            $updated = Promotion::query()
                ->whereKey($promotion->id)
                ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                ->update(['used_count' => DB::raw('used_count + 1')]);
            if ($updated !== 1) {
                throw new RuntimeException('This promotion has been fully redeemed.');
            }

            if ($promotion->per_customer_limit !== null && $userId !== null
                && PromotionRedemption::query()->where('promotion_id', $promotion->id)->where('user_id', $userId)->count() >= $promotion->per_customer_limit) {
                throw new RuntimeException('You have already used this promotion the maximum number of times.');
            }

            return PromotionRedemption::query()->create([
                'promotion_id' => $promotion->id,
                'user_id' => $userId,
                'reference' => $reference,
                'discount_paisa' => $discountPaisa,
            ]);
        });
    }

    private function couponProblem(?Promotion $coupon, PricingContext $context): ?string
    {
        return match (true) {
            $coupon === null => 'This coupon code is not valid.',
            ! $coupon->isEffectiveAt($context->at) => 'This coupon is not active or has expired.',
            ! $coupon->allowsAudience($context->audience) => 'This coupon is not available for your account type.',
            $coupon->isExhausted() => 'This coupon has been fully redeemed.',
            $coupon->per_customer_limit !== null && $context->userId === null => 'Sign in to use this coupon.',
            $this->customerLimitReached($coupon, $context) => 'You have already used this coupon the maximum number of times.',
            default => null,
        };
    }

    private function customerLimitReached(Promotion $promotion, PricingContext $context): bool
    {
        if ($promotion->per_customer_limit === null) {
            return false;
        }
        if ($context->userId === null) {
            return true;
        }

        return PromotionRedemption::query()->where('promotion_id', $promotion->id)->where('user_id', $context->userId)->count() >= $promotion->per_customer_limit;
    }

    /**
     * @param  list<array{key: string, product_id: int|null, line_paisa: int, trade_priced: bool, is_bundle: bool}>  $lines
     */
    private function eligibleBase(?Promotion $promotion, array $lines, bool $includeTradeAndBundles = false): int
    {
        $stackable = $promotion?->stackable ?? $includeTradeAndBundles;
        $productIds = $promotion?->scope === Promotion::SCOPE_PRODUCTS ? $promotion->products->pluck('id')->all() : null;

        return array_sum(array_map(function (array $line) use ($stackable, $productIds): int {
            if (! $stackable && ($line['trade_priced'] || $line['is_bundle'])) {
                return 0;
            }
            if ($productIds !== null && ! in_array($line['product_id'], $productIds, true)) {
                return 0;
            }

            return $line['line_paisa'];
        }, $lines));
    }

    private function discountFor(Promotion $promotion, int $base): int
    {
        $discount = match ($promotion->discount_type) {
            DiscountType::Percentage => Taka::divideRoundHalfUp($base * $promotion->discount_value, 10000),
            DiscountType::FixedAmount => $promotion->discount_value,
        };

        if ($promotion->max_discount_paisa !== null) {
            $discount = min($discount, $promotion->max_discount_paisa);
        }

        return min($discount, $base);
    }
}
