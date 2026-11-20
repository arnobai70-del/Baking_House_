<?php

namespace App\Services\Reports;

use App\Enums\CustomerAudience;
use App\Enums\LeadStage;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\Refund;
use App\Models\User;
use App\Services\Pricing\PriceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reports computed from the live database only (orders, order lines, refunds, redemptions,
 * stock, quotations, CRM leads). Nothing is estimated or sampled.
 *
 * Definitions (all integer paisa; orders are counted by the Asia/Dhaka date they were PLACED):
 * - Gross sales     = sum of order-line amounts (subtotal) of non-cancelled orders
 * - Discounts       = order-level promotion discounts on those orders
 * - Net sales       = gross sales − discounts
 * - Delivery        = delivery charges billed on those orders
 * - Billed          = net sales + delivery (= order totals)
 * - Refunds         = refunds RECORDED in the period (any order)
 * - Net revenue     = billed − refunds
 * Cancelled orders are reported separately and never counted as sales. "Paid" uses the order's
 * payment status (cash on delivery counts once staff record the cash).
 */
class ReportService
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} UTC bounds for a Dhaka date range
     */
    public static function bounds(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->timezone('Asia/Dhaka')->startOfDay()->utc(), $to->timezone('Asia/Dhaka')->endOfDay()->utc()];
    }

    /**
     * @return array<string, int>
     */
    public function financial(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $orders = Order::query()->whereBetween('placed_at', [$start, $end]);

        $sales = (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('count(*) as orders, coalesce(sum(subtotal_paisa),0) as gross, coalesce(sum(discount_paisa),0) as discounts, coalesce(sum(delivery_paisa),0) as delivery, coalesce(sum(total_paisa),0) as billed')
            ->first();
        $paidStatuses = [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value];
        $paid = (int) (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value)->whereIn('payment_status', $paidStatuses)->sum('total_paisa');
        $cancelled = (clone $orders)->where('status', OrderStatus::Cancelled->value)->selectRaw('count(*) as orders, coalesce(sum(total_paisa),0) as total')->first();
        $refunds = (int) Refund::query()->whereBetween('created_at', [$start, $end])->sum('amount_paisa');

        $billed = (int) $sales->billed;

        return [
            'orders' => (int) $sales->orders,
            'gross' => (int) $sales->gross,
            'discounts' => (int) $sales->discounts,
            'net_sales' => (int) $sales->gross - (int) $sales->discounts,
            'delivery' => (int) $sales->delivery,
            'billed' => $billed,
            'refunds' => $refunds,
            'net_revenue' => $billed - $refunds,
            'paid' => $paid,
            'unpaid' => $billed - $paid,
            'cancelled_orders' => (int) $cancelled->orders,
            'cancelled_amount' => (int) $cancelled->total,
            'average_order' => (int) $sales->orders > 0 ? intdiv($billed, (int) $sales->orders) : 0,
        ];
    }

    /**
     * Billed amount and order count per Dhaka day (every day in the range, zero-filled).
     *
     * @return list<array{date: string, orders: int, billed: int}>
     */
    public function daily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $days = [];
        for ($day = $from->timezone('Asia/Dhaka')->startOfDay(); $day->lte($to->timezone('Asia/Dhaka')); $day = $day->addDay()) {
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'orders' => 0, 'billed' => 0];
        }

        Order::query()->whereBetween('placed_at', [$start, $end])->where('status', '!=', OrderStatus::Cancelled->value)
            ->select(['placed_at', 'total_paisa'])->orderBy('id')
            ->each(function (Order $order) use (&$days): void {
                $key = $order->placed_at->timezone('Asia/Dhaka')->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['orders']++;
                    $days[$key]['billed'] += $order->total_paisa;
                }
            });

        return array_values($days);
    }

    /**
     * @return list<array{status: OrderStatus, orders: int, total: int}>
     */
    public function byStatus(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $rows = Order::query()->whereBetween('placed_at', [$start, $end])->selectRaw('status, count(*) as orders, coalesce(sum(total_paisa),0) as total')->groupBy('status')->get()->keyBy(fn ($row) => $row->getRawOriginal('status'));

        return array_map(fn (OrderStatus $status) => [
            'status' => $status, 'orders' => (int) ($rows[$status->value]->orders ?? 0), 'total' => (int) ($rows[$status->value]->total ?? 0),
        ], OrderStatus::cases());
    }

    /**
     * @return list<array{status: PaymentStatus, orders: int, total: int}>
     */
    public function byPaymentStatus(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $rows = Order::query()->whereBetween('placed_at', [$start, $end])->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('payment_status, count(*) as orders, coalesce(sum(total_paisa),0) as total')->groupBy('payment_status')->get()->keyBy(fn ($row) => $row->getRawOriginal('payment_status'));

        return array_map(fn (PaymentStatus $status) => [
            'status' => $status, 'orders' => (int) ($rows[$status->value]->orders ?? 0), 'total' => (int) ($rows[$status->value]->total ?? 0),
        ], PaymentStatus::cases());
    }

    /**
     * Retail / business / reseller split of non-cancelled orders.
     *
     * @return list<array{audience: string, orders: int, gross: int, discounts: int, billed: int}>
     */
    public function byAudience(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $rows = Order::query()->whereBetween('placed_at', [$start, $end])->where('status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('audience, count(*) as orders, coalesce(sum(subtotal_paisa),0) as gross, coalesce(sum(discount_paisa),0) as discounts, coalesce(sum(total_paisa),0) as billed')
            ->groupBy('audience')->get()->keyBy(fn ($row) => $row->getRawOriginal('audience'));

        return array_map(fn (CustomerAudience $audience) => [
            'audience' => $audience->value,
            'orders' => (int) ($rows[$audience->value]->orders ?? 0),
            'gross' => (int) ($rows[$audience->value]->gross ?? 0),
            'discounts' => (int) ($rows[$audience->value]->discounts ?? 0),
            'billed' => (int) ($rows[$audience->value]->billed ?? 0),
        ], CustomerAudience::cases());
    }

    /**
     * Best sellers by line revenue: catalog lines plus the catalog items inside quotations.
     * Bundles are reported as bundles (their price is not split across items).
     *
     * @return list<array{sku: string, name: string, quantity: int, quantity_label: string, revenue: int, orders: int}>
     */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $totals = [];
        foreach ($this->lines($from, $to) as $line) {
            $key = $line['type'] === 'bundle' ? 'bundle:'.$line['name'] : (string) $line['sku'];
            $totals[$key] ??= ['sku' => $line['type'] === 'bundle' ? 'Bundle' : (string) $line['sku'], 'name' => $line['name'], 'variant_id' => $line['variant_id'], 'quantity' => 0, 'revenue' => 0, 'orders' => []];
            $totals[$key]['quantity'] += $line['quantity'];
            $totals[$key]['revenue'] += $line['revenue'];
            $totals[$key]['orders'][$line['order_id']] = true;
        }

        usort($totals, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue'] ?: strcmp($a['sku'], $b['sku']));
        $variants = ProductVariant::query()->whereIn('id', array_filter(array_column($totals, 'variant_id')))->get()->keyBy('id');

        return array_map(fn (array $row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'quantity' => $row['quantity'],
            'quantity_label' => isset($variants[$row['variant_id']]) ? PriceResolver::quantityLabel($variants[$row['variant_id']], $row['quantity']) : '× '.$row['quantity'],
            'revenue' => $row['revenue'],
            'orders' => count($row['orders']),
        ], array_slice($totals, 0, $limit));
    }

    /**
     * Line revenue by the product's primary category (bundles excluded).
     *
     * @return list<array{category: string, revenue: int, orders: int}>
     */
    public function topCategories(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        $variantIds = [];
        $lines = array_values(array_filter($this->lines($from, $to), fn (array $line) => $line['variant_id'] !== null));
        foreach ($lines as $line) {
            $variantIds[$line['variant_id']] = true;
        }
        $categories = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.primary_category_id')
            ->whereIn('product_variants.id', array_keys($variantIds))
            ->pluck('categories.name', 'product_variants.id');

        $totals = [];
        foreach ($lines as $line) {
            $name = (string) ($categories[$line['variant_id']] ?? 'Uncategorised');
            $totals[$name] ??= ['category' => $name, 'revenue' => 0, 'orders' => []];
            $totals[$name]['revenue'] += $line['revenue'];
            $totals[$name]['orders'][$line['order_id']] = true;
        }
        usort($totals, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        return array_map(fn (array $row) => ['category' => $row['category'], 'revenue' => $row['revenue'], 'orders' => count($row['orders'])], array_slice($totals, 0, $limit));
    }

    /**
     * Current stock position (not date-ranged).
     *
     * @return array{low: list<ProductVariant>, out: list<ProductVariant>, active: int}
     */
    public function inventory(): array
    {
        $variants = ProductVariant::query()->where('is_active', true)->where('pricing_mode', '!=', 'quote_only')->with('product:id,name,slug')->orderBy('sku')->get();

        return [
            'low' => $variants->filter(fn (ProductVariant $variant) => $variant->isLowStock())->values()->all(),
            'out' => $variants->filter(fn (ProductVariant $variant) => $variant->availableQuantity() <= 0)->values()->all(),
            'active' => $variants->count(),
        ];
    }

    /**
     * Coupon / promotion performance from redemptions of orders placed in the range (cancelled
     * orders give their redemption back, so they are not counted).
     *
     * @return list<array{name: string, code: ?string, uses: int, discount: int, billed: int}>
     */
    public function promotions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);

        return DB::table('promotion_redemptions')
            ->join('promotions', 'promotions.id', '=', 'promotion_redemptions.promotion_id')
            ->join('orders', 'orders.number', '=', 'promotion_redemptions.reference')
            ->whereBetween('orders.placed_at', [$start, $end])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('promotions.id', 'promotions.name', 'promotions.code')
            ->orderByDesc(DB::raw('sum(promotion_redemptions.discount_paisa)'))
            ->get(['promotions.name', 'promotions.code', DB::raw('count(*) as uses'), DB::raw('sum(promotion_redemptions.discount_paisa) as discount'), DB::raw('sum(orders.total_paisa) as billed')])
            ->map(fn ($row) => ['name' => $row->name, 'code' => $row->code, 'uses' => (int) $row->uses, 'discount' => (int) $row->discount, 'billed' => (int) $row->billed])
            ->all();
    }

    /**
     * Acquisition and repeat purchasing.
     *
     * @return array{new_accounts: int, buyers: int, first_time: int, repeat: int, repeat_rate: ?int, guest_orders: int}
     */
    public function customers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $active = fn ($query) => $query->where('status', '!=', OrderStatus::Cancelled->value);

        $buyerIds = Order::query()->whereBetween('placed_at', [$start, $end])->tap($active)->whereNotNull('user_id')->distinct()->pluck('user_id');
        $firstOrders = Order::query()->tap($active)->whereIn('user_id', $buyerIds)->groupBy('user_id')->selectRaw('user_id, min(placed_at) as first_at, count(*) as total')->get();

        $firstTime = $firstOrders->filter(fn ($row) => CarbonImmutable::parse($row->first_at, 'UTC')->betweenIncluded($start, $end))->count();
        $repeat = $firstOrders->filter(fn ($row) => (int) $row->total >= 2)->count();

        return [
            'new_accounts' => User::query()->whereDoesntHave('roles')->whereBetween('created_at', [$start, $end])->count(),
            'buyers' => $buyerIds->count(),
            'first_time' => $firstTime,
            'repeat' => $repeat,
            'repeat_rate' => $buyerIds->count() > 0 ? (int) round($repeat * 100 / $buyerIds->count()) : null,
            'guest_orders' => Order::query()->whereBetween('placed_at', [$start, $end])->tap($active)->whereNull('user_id')->count(),
        ];
    }

    /**
     * RFQ funnel for quotations REQUESTED in the range, plus the current CRM pipeline.
     *
     * @return array{requested: int, quoted: int, accepted: int, ordered: int, lost: int, conversion: ?int, quoted_value: int, ordered_value: int, pipeline: list<array{stage: LeadStage, leads: int, value: int}>, queue: int}
     */
    public function rfq(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $quotations = Quotation::query()->whereBetween('created_at', [$start, $end]);
        $requested = (clone $quotations)->count();
        $ordered = (clone $quotations)->where('status', 'ordered');

        $stages = CrmLead::query()->selectRaw('stage, count(*) as leads, coalesce(sum(estimated_value_paisa),0) as value')->groupBy('stage')->get()->keyBy(fn ($row) => $row->getRawOriginal('stage'));

        return [
            'requested' => $requested,
            'quoted' => (clone $quotations)->whereNotNull('quoted_at')->count(),
            'accepted' => (clone $quotations)->whereIn('status', ['accepted', 'ordered'])->count(),
            'ordered' => (clone $ordered)->count(),
            'lost' => (clone $quotations)->whereIn('status', ['declined', 'closed', 'expired'])->count(),
            'conversion' => $requested > 0 ? (int) round((clone $ordered)->count() * 100 / $requested) : null,
            'quoted_value' => (int) (clone $quotations)->whereNotNull('quoted_at')->sum('total_paisa'),
            'ordered_value' => (int) (clone $ordered)->sum('total_paisa'),
            'pipeline' => array_map(fn (LeadStage $stage) => [
                'stage' => $stage, 'leads' => (int) ($stages[$stage->value]->leads ?? 0), 'value' => (int) ($stages[$stage->value]->value ?? 0),
            ], LeadStage::cases()),
            'queue' => CrmLead::query()->whereNull('assigned_to')->whereIn('stage', array_map(fn ($stage) => $stage->value, LeadStage::open()))->count(),
        ];
    }

    /**
     * Sellable lines of non-cancelled orders placed in the range, with quotation components
     * expanded to their catalog items.
     *
     * @return list<array{order_id: int, type: string, variant_id: ?int, sku: ?string, name: string, quantity: int, revenue: int}>
     */
    private function lines(CarbonImmutable $from, CarbonImmutable $to): array
    {
        [$start, $end] = self::bounds($from, $to);
        $lines = [];

        DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.placed_at', [$start, $end])->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->orderBy('order_items.id')
            ->select(['order_items.order_id', 'order_items.type', 'order_items.product_variant_id', 'order_items.sku', 'order_items.name', 'order_items.quantity', 'order_items.line_paisa', 'order_items.components'])
            ->get()->each(function ($item) use (&$lines): void {
                if ($item->type === 'quotation') {
                    foreach (json_decode((string) $item->components, true) ?: [] as $component) {
                        $lines[] = ['order_id' => $item->order_id, 'type' => $component['variant_id'] === null ? 'custom' : 'item', 'variant_id' => $component['variant_id'],
                            'sku' => $component['sku'] ?? 'Custom', 'name' => (string) $component['name'], 'quantity' => (int) $component['quantity'], 'revenue' => (int) $component['line_paisa']];
                    }

                    return;
                }
                $lines[] = ['order_id' => $item->order_id, 'type' => $item->type, 'variant_id' => $item->product_variant_id, 'sku' => $item->sku,
                    'name' => (string) $item->name, 'quantity' => (int) $item->quantity, 'revenue' => (int) $item->line_paisa];
            });

        return array_values(array_filter($lines, fn (array $line) => $line['type'] !== 'custom'));
    }
}
