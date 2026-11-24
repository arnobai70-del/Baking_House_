<?php

namespace App\Services\Cart;

use App\Models\Bundle;
use App\Models\CakeDesign;
use App\Models\CakeUpload;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\QuoteService;
use App\Services\Promotions\BundlePricer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guest and customer carts. A cart stores only identities and quantities; every read re-prices
 * it on the server for the buyer's CURRENT context (retail / business / reseller / contract).
 *
 * Merge rule on sign-in: guest lines are added to the customer's cart (same item → quantities
 * summed so volume tiers see the total); lines that become invalid stay in the cart flagged with
 * the reason, never silently changed. The customer's own coupon wins over the guest one.
 */
class CartService
{
    public const COOKIE = 'bh_cart';

    public const COOKIE_MINUTES = 60 * 24 * 30;

    public function __construct(
        private PriceResolver $prices,
        private BundlePricer $bundles,
        private QuoteService $quotes,
    ) {}

    /**
     * The cart for this request: the signed-in user's cart, else the guest cart from the cookie.
     * Creates one when $create is true.
     */
    public function forRequest(Request $request, bool $create = false): ?Cart
    {
        $user = $request->user();
        if ($user !== null) {
            return $create ? $this->forUser($user) : Cart::query()->where('user_id', $user->id)->first();
        }

        $token = (string) $request->cookie(self::COOKIE, '');
        $cart = strlen($token) === 40 ? Cart::query()->whereNull('user_id')->where('token', $token)->first() : null;

        if ($cart === null && $create) {
            $cart = Cart::query()->create(['token' => Str::random(40), 'last_activity_at' => now()]);
        }

        return $cart;
    }

    public function forUser(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id], ['token' => Str::random(40), 'last_activity_at' => now()]);
    }

    /**
     * @throws ValidationException when the resulting quantity is not sellable for this buyer
     */
    public function addVariant(Cart $cart, ProductVariant $variant, int $quantity, PricingContext $context): CartItem
    {
        $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
        $total = ($existing?->quantity ?? 0) + $quantity;

        $quote = $this->prices->quote($variant, $total, $context);
        if (! $quote->ok) {
            throw ValidationException::withMessages(['quantity' => $existing !== null
                ? $quote->error.' (You already have '.PriceResolver::quantityLabel($variant, $existing->quantity).' in your cart.)'
                : $quote->error]);
        }

        $item = $existing ?? new CartItem(['product_variant_id' => $variant->id]);
        $item->quantity = $total;
        $item->cart()->associate($cart);
        $item->save();
        $this->touch($cart);

        return $item;
    }

    /**
     * @throws ValidationException
     */
    public function addBundle(Cart $cart, Bundle $bundle, int $quantity, PricingContext $context): CartItem
    {
        $existing = $cart->items()->where('bundle_id', $bundle->id)->first();
        $total = ($existing?->quantity ?? 0) + $quantity;

        $quote = $this->bundles->quote($bundle, $total, $context);
        if (! $quote['ok']) {
            throw ValidationException::withMessages(['quantity' => (string) $quote['error']]);
        }

        $item = $existing ?? new CartItem(['bundle_id' => $bundle->id]);
        $item->quantity = $total;
        $item->cart()->associate($cart);
        $item->save();
        $this->touch($cart);

        return $item;
    }

    /**
     * Put an accepted quotation in the cart as one fixed line (quantity always 1).
     *
     * @throws ValidationException when the quotation cannot be ordered in this buying context
     */
    public function addQuotation(Cart $cart, Quotation $quotation, PricingContext $context): CartItem
    {
        if (! $quotation->canBeOrderedBy($context->organization)) {
            throw ValidationException::withMessages(['quotation' => __('This quotation is not available for the account you are buying for.')]);
        }

        $item = $cart->items()->firstOrCreate(['quotation_id' => $quotation->id], ['quantity' => 1]);
        $this->touch($cart);

        return $item;
    }

    /**
     * Put a custom cake design in the cart as one line (quantity always 1). The design is priced on every read.
     */
    public function addCakeDesign(Cart $cart, CakeDesign $design): CartItem
    {
        $item = $cart->items()->firstOrCreate(['cake_design_id' => $design->id], ['quantity' => 1]);
        $design->forceFill(['status' => CakeDesign::IN_CART])->save();
        $this->touch($cart);

        return $item;
    }

    /**
     * Set an exact quantity (0 removes). Invalid quantities are rejected, not adjusted.
     * Quotation lines can only be removed: their quantities are fixed by the quote.
     *
     * @throws ValidationException
     */
    public function updateQuantity(CartItem $item, int $quantity, PricingContext $context): void
    {
        if ($quantity <= 0) {
            $item->delete();

            return;
        }

        if ($item->quotation_id !== null) {
            throw ValidationException::withMessages(['quantity' => __('Quoted quantities are fixed. Request a new quote to change them.')]);
        }
        if ($item->cake_design_id !== null) {
            throw ValidationException::withMessages(['quantity' => __('Each custom cake is one design. Duplicate the design to order another.')]);
        }

        if ($item->product_variant_id !== null) {
            $quote = $this->prices->quote($item->variant()->firstOrFail(), $quantity, $context);
            if (! $quote->ok) {
                throw ValidationException::withMessages(['quantity' => (string) $quote->error]);
            }
        } else {
            $quote = $this->bundles->quote($item->bundle()->firstOrFail(), $quantity, $context);
            if (! $quote['ok']) {
                throw ValidationException::withMessages(['quantity' => (string) $quote['error']]);
            }
        }

        $item->forceFill(['quantity' => $quantity])->save();
        $this->touch($item->cart()->first());
    }

    public function setCoupon(Cart $cart, ?string $code): void
    {
        $cart->forceFill(['coupon_code' => $code === null || trim($code) === '' ? null : strtoupper(trim($code))])->save();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->forceFill(['coupon_code' => null])->save();
    }

    /**
     * Move a guest cart (from the request cookie) into the user's cart on sign-in.
     */
    public function mergeGuestCart(?string $guestToken, User $user): void
    {
        if ($guestToken === null || strlen($guestToken) !== 40) {
            return;
        }

        $guest = Cart::query()->whereNull('user_id')->where('token', $guestToken)->with('items')->first();
        if ($guest === null) {
            return;
        }

        DB::transaction(function () use ($guest, $user): void {
            $cart = $this->forUser($user);

            // The guest's cake designs and private cake photos move to the account with the cart.
            $guestHash = hash('sha256', $guest->token);
            CakeDesign::query()->whereNull('user_id')->where('owner_token_hash', $guestHash)->update(['user_id' => $user->id, 'owner_token_hash' => null]);
            CakeUpload::query()->whereNull('user_id')->where('owner_token_hash', $guestHash)->update(['user_id' => $user->id, 'owner_token_hash' => null]);

            foreach ($guest->items as $guestItem) {
                if ($guestItem->cake_design_id !== null) {
                    $cart->items()->firstOrCreate(['cake_design_id' => $guestItem->cake_design_id], ['quantity' => 1]);
                    CakeDesign::query()->whereKey($guestItem->cake_design_id)->whereNull('user_id')->update(['user_id' => $user->id]);

                    continue;
                }
                $column = $guestItem->product_variant_id !== null ? 'product_variant_id' : 'bundle_id';
                $target = $cart->items()->where($column, $guestItem->{$column})->first();

                if ($target !== null) {
                    $target->forceFill(['quantity' => $target->quantity + $guestItem->quantity])->save();
                } else {
                    $cart->items()->create([$column => $guestItem->{$column}, 'quantity' => $guestItem->quantity]);
                }
            }

            if ($cart->coupon_code === null && $guest->coupon_code !== null) {
                $cart->forceFill(['coupon_code' => $guest->coupon_code])->save();
            }

            $guest->delete();
            $this->touch($cart);
        });
    }

    /**
     * Server-side price of the whole cart for this buyer, with cart item ids attached to lines.
     *
     * @return array<string, mixed>
     */
    public function quote(Cart $cart, PricingContext $context): array
    {
        $items = $cart->items()->with(['variant:id,sku,product_id,pricing_mode', 'bundle:id,slug', 'quotation:id,number'])->get();

        $variantLines = $items->whereNotNull('product_variant_id')->filter(fn (CartItem $item) => $item->variant !== null)
            ->map(fn (CartItem $item) => ['sku' => $item->variant->sku, 'quantity' => $item->quantity])->values()->all();
        $bundleLines = $items->whereNotNull('bundle_id')->filter(fn (CartItem $item) => $item->bundle !== null)
            ->map(fn (CartItem $item) => ['slug' => $item->bundle->slug, 'quantity' => $item->quantity])->values()->all();
        $quotationNumbers = $items->whereNotNull('quotation_id')->filter(fn (CartItem $item) => $item->quotation !== null)
            ->map(fn (CartItem $item) => $item->quotation->number)->values()->all();
        $cakeDesignIds = $items->whereNotNull('cake_design_id')->pluck('cake_design_id')->map(fn ($id) => (int) $id)->values()->all();

        $quote = $variantLines === [] && $bundleLines === [] && $quotationNumbers === [] && $cakeDesignIds === []
            ? ['lines' => [], 'subtotal_paisa' => 0, 'discount_paisa' => 0, 'total_paisa' => 0, 'promotions' => [], 'coupon' => null, 'valid' => false, 'audience' => $context->audience->value]
            : $this->quotes->quote($variantLines, $bundleLines, $context, $cart->coupon_code, $quotationNumbers, $cakeDesignIds);

        $bySku = $items->filter(fn (CartItem $item) => $item->variant !== null)->mapWithKeys(fn (CartItem $item) => [$item->variant->sku => $item]);
        $bySlug = $items->filter(fn (CartItem $item) => $item->bundle !== null)->mapWithKeys(fn (CartItem $item) => [$item->bundle->slug => $item]);
        $byNumber = $items->filter(fn (CartItem $item) => $item->quotation !== null)->mapWithKeys(fn (CartItem $item) => [$item->quotation->number => $item]);
        $byDesign = $items->whereNotNull('cake_design_id')->keyBy('cake_design_id');

        $quote['lines'] = array_map(function (array $line) use ($bySku, $bySlug, $byNumber, $byDesign): array {
            $item = match ($line['type']) {
                'bundle' => $bySlug->get($line['slug']),
                'quotation' => $byNumber->get($line['number']),
                'cake' => $byDesign->get($line['cake_design_id']),
                default => $bySku->get($line['sku']),
            };
            $line['cart_item_id'] = $item?->id;
            if ($line['type'] === 'cake') {
                $line['base_quantity'] = 1;
                $line['is_weight'] = false;

                return $line; // product_variant_id comes from the design (the cake line has no variant column)
            }
            $line['product_variant_id'] = $item?->product_variant_id;
            $line['bundle_id'] = $item?->bundle_id;
            $line['base_quantity'] = $item?->quantity;
            $line['is_weight'] = $item?->variant?->isWeight() ?? false;

            return $line;
        }, $quote['lines']);
        $quote['item_count'] = $items->count();

        return $quote;
    }

    public function itemCount(?Cart $cart): int
    {
        return $cart === null ? 0 : $cart->items()->count();
    }

    private function touch(?Cart $cart): void
    {
        $cart?->forceFill(['last_activity_at' => now()])->saveQuietly();
    }
}
