<?php

namespace App\Services\Portal;

use App\Models\Cart;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use App\Support\QuantityInput;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Bulk ordering for business and reseller portals: "SKU, quantity" per line (pasted text or a CSV
 * file). Every line is validated on the server with the buyer's own prices, weight rules and stock
 * and is either accepted as-is or rejected with a reason; nothing is silently adjusted. Repeated
 * SKUs are merged so volume tiers see the total.
 */
class BulkOrderService
{
    public const MAX_LINES = 200;

    public function __construct(private PriceResolver $prices, private CartService $carts) {}

    /**
     * @return list<array{line: int, input: string, sku: string, variant: ?ProductVariant, quantity: ?int, quantity_label: ?string, ok: bool, error: ?string, line_paisa: ?int, rate_source: ?string}>
     *
     * @throws ValidationException
     */
    public function check(string $text, PricingContext $context): array
    {
        $rows = $this->rows($text);
        $variants = ProductVariant::query()
            ->whereIn('sku', array_unique(array_column($rows, 'sku')))
            ->whereHas('product', fn ($product) => $product->visible())
            ->with('product:id,name')
            ->get()
            ->keyBy('sku');

        $parsed = [];
        $totals = [];
        foreach ($rows as $row) {
            $variant = $variants->get($row['sku']);
            $result = $row + ['variant' => $variant, 'quantity' => null, 'quantity_label' => null, 'ok' => false, 'error' => null, 'line_paisa' => null, 'rate_source' => null];

            if ($variant === null) {
                $parsed[] = ['error' => __('Unknown SKU.')] + $result;

                continue;
            }

            try {
                $quantity = QuantityInput::parse($variant, $row['quantity_input']);
            } catch (InvalidArgumentException $e) {
                $parsed[] = ['error' => $e->getMessage()] + $result;

                continue;
            }

            $totals[$variant->sku] = ($totals[$variant->sku] ?? 0) + $quantity;
            $parsed[] = ['quantity' => $quantity, 'quantity_label' => QuantityInput::label($variant, $quantity)] + $result;
        }

        /* Price each SKU once on its merged total, then report the result on every line of that SKU. */
        $quotes = [];
        foreach ($totals as $sku => $quantity) {
            $quotes[$sku] = $this->prices->quote($variants->get($sku), $quantity, $context);
        }

        return array_map(function (array $row) use ($quotes, $totals): array {
            if ($row['error'] !== null) {
                return $this->publicRow($row);
            }

            $quote = $quotes[$row['sku']];
            if (! $quote->ok) {
                $error = $totals[$row['sku']] !== $row['quantity']
                    ? __(':error (total of all lines for this SKU).', ['error' => $quote->error])
                    : (string) $quote->error;

                return $this->publicRow(['error' => $error] + $row);
            }

            return $this->publicRow([
                'ok' => true,
                'rate_source' => $quote->rateSource,
                'line_paisa' => $totals[$row['sku']] === $row['quantity'] ? $quote->linePaisa : null,
            ] + $row);
        }, $parsed);
    }

    /**
     * Add every valid line to the cart (quantities of the same SKU merged). Returns what was added
     * and what was refused.
     *
     * @param  list<array<string, mixed>>  $checked
     * @return array{added: int, failed: list<string>}
     */
    public function addToCart(Cart $cart, array $checked, PricingContext $context): array
    {
        $totals = [];
        $variants = [];
        foreach ($checked as $row) {
            if ($row['ok']) {
                $totals[$row['sku']] = ($totals[$row['sku']] ?? 0) + $row['quantity'];
                $variants[$row['sku']] = $row['variant'];
            }
        }

        $added = 0;
        $failed = [];
        foreach ($totals as $sku => $quantity) {
            try {
                $this->carts->addVariant($cart, $variants[$sku], $quantity, $context);
                $added++;
            } catch (ValidationException $e) {
                $failed[] = $sku.': '.collect($e->errors())->flatten()->first();
            }
        }

        return ['added' => $added, 'failed' => $failed];
    }

    /**
     * @return list<array{line: int, input: string, sku: string, quantity_input: string}>
     *
     * @throws ValidationException
     */
    private function rows(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? '';
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $index => $raw) {
            $input = trim($raw);
            if ($input === '' || str_starts_with($input, '#')) {
                continue;
            }

            $parts = array_map('trim', str_getcsv(str_replace(["\t", ';'], ',', $input), ',', '"', ''));
            $sku = strtoupper($parts[0] ?? '');

            if ($rows === [] && in_array(strtolower($parts[0] ?? ''), ['sku', 'code'], true)) {
                continue;
            }

            $rows[] = ['line' => $index + 1, 'input' => mb_substr($input, 0, 120), 'sku' => mb_substr($sku, 0, 64), 'quantity_input' => (string) ($parts[1] ?? '')];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['lines' => __('Enter at least one line: SKU, quantity.')]);
        }
        if (count($rows) > self::MAX_LINES) {
            throw ValidationException::withMessages(['lines' => __('A bulk order can have at most :max lines.', ['max' => self::MAX_LINES])]);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function publicRow(array $row): array
    {
        unset($row['quantity_input']);
        $row['name'] = $row['variant']?->product?->name;

        return $row;
    }
}
