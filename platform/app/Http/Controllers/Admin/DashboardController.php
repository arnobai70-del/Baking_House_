<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Enums\QuotationStatus;
use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Orders that wait on staff (payment check, packing, dispatch); unpaid orders wait on the customer. */
    public const ORDERS_NEEDING_ACTION = [OrderStatus::PaymentReview, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Packed];

    public function __invoke(Request $request): View
    {
        return view('admin.dashboard', [
            'counts' => $this->operationalCounts($request->user()),
            'orderTrend' => $request->user()->can(StaffPermission::ManageOrders->value) ? $this->orderTrend() : null,
            'pendingOrganizations' => Organization::query()
                ->where('status', OrganizationStatus::Pending->value)
                ->with('members:id,name,phone')
                ->oldest()
                ->get(),
        ]);
    }

    /** Days shown in the orders-per-day chart. */
    public const TREND_DAYS = 14;

    /**
     * Orders placed per day over the last TREND_DAYS days, from real order records (counts only, no money).
     *
     * @return list<array{date: CarbonImmutable, count: int}>
     */
    private function orderTrend(): array
    {
        $start = CarbonImmutable::today()->subDays(self::TREND_DAYS - 1);
        $perDay = Order::query()->whereNotNull('placed_at')->where('placed_at', '>=', $start)
            ->selectRaw('date(placed_at) as day, count(*) as orders')->groupBy('day')->pluck('orders', 'day');

        return array_map(fn (int $offset) => [
            'date' => $start->addDays($offset),
            'count' => (int) ($perDay[$start->addDays($offset)->toDateString()] ?? 0),
        ], range(0, self::TREND_DAYS - 1));
    }

    /**
     * Live work queues, each shown only to staff who hold the permission for that screen (no money figures).
     *
     * @return list<array{label: string, value: int, hint: string, url: string}>
     */
    private function operationalCounts(User $user): array
    {
        $counts = [];

        if ($user->can(StaffPermission::ManageOrders->value)) {
            $counts[] = [
                'label' => 'Orders needing action',
                'value' => Order::query()->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, self::ORDERS_NEEDING_ACTION))->count(),
                'hint' => 'Payment review, confirmed, processing or packed',
                'url' => route('admin.orders.index'),
            ];
        }
        if ($user->can(StaffPermission::ManageInventory->value)) {
            $counts[] = [
                'label' => 'Low-stock SKUs',
                'value' => ProductVariant::query()->where('low_stock_threshold', '>', 0)->whereRaw('stock_quantity - reserved_quantity <= low_stock_threshold')->count(),
                'hint' => 'At or below their low-stock level',
                'url' => route('admin.inventory.index', ['low' => 1]),
            ];
        }
        if ($user->can(StaffPermission::ApproveMedia->value)) {
            $counts[] = [
                'label' => 'Photos awaiting approval',
                'value' => ProductImage::query()->where('review_status', ProductImage::STATUS_PENDING)->count(),
                'hint' => 'Uploaded, not yet reviewed',
                'url' => route('admin.media.queue'),
            ];
        }
        if ($user->can(StaffPermission::ManageQuotations->value)) {
            $counts[] = [
                'label' => 'Open quotation requests',
                'value' => Quotation::query()->where('status', QuotationStatus::Requested->value)->count(),
                'hint' => 'Waiting for a quote',
                'url' => route('admin.quotations.index', ['status' => QuotationStatus::Requested->value]),
            ];
        }

        return $counts;
    }
}
