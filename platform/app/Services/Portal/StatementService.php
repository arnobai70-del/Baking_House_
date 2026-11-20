<?php

namespace App\Services\Portal;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Organization;
use App\Support\Taka;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Monthly account statement for one organization (Asia/Dhaka calendar month): every order placed
 * in the month with what was charged, paid and refunded. Totals come from the immutable order
 * snapshots. Cancelled orders are listed but count nothing. Informational only: no credit terms.
 */
class StatementService
{
    /**
     * @return array{month: CarbonImmutable, orders: Collection<int, Order>, rows: list<array<string, mixed>>, totals: array{charged: int, paid: int, refunded: int, balance: int}}
     */
    public function forMonth(Organization $organization, CarbonImmutable $month): array
    {
        $start = $month->timezone('Asia/Dhaka')->startOfMonth();
        $end = $start->endOfMonth();

        $orders = Order::query()
            ->where('organization_id', $organization->id)
            ->whereBetween('placed_at', [$start->utc(), $end->utc()])
            ->orderBy('placed_at')
            ->get();

        $rows = [];
        $totals = ['charged' => 0, 'paid' => 0, 'refunded' => 0, 'balance' => 0];
        foreach ($orders as $order) {
            $cancelled = $order->status === OrderStatus::Cancelled;
            $charged = $cancelled ? 0 : $order->total_paisa;
            $paid = $order->isPaid() || $order->refunded_paisa > 0 ? $order->total_paisa : 0;
            $refunded = (int) $order->refunded_paisa;
            $balance = $cancelled ? 0 : max(0, $charged - $paid);

            $rows[] = ['order' => $order, 'charged' => $charged, 'paid' => $paid, 'refunded' => $refunded, 'balance' => $balance, 'cancelled' => $cancelled];
            $totals['charged'] += $charged;
            $totals['paid'] += $paid;
            $totals['refunded'] += $refunded;
            $totals['balance'] += $balance;
        }

        return ['month' => $start, 'orders' => $orders, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * @param  array{rows: list<array<string, mixed>>, totals: array<string, int>}  $statement
     * @return list<list<string>>
     */
    public function csv(array $statement): array
    {
        $lines = [['Date', 'Order', 'Status', 'Payment', 'Charged (BDT)', 'Paid (BDT)', 'Refunded (BDT)', 'Balance due (BDT)']];
        foreach ($statement['rows'] as $row) {
            $order = $row['order'];
            $lines[] = [
                $order->placed_at->timezone('Asia/Dhaka')->format('Y-m-d'), $order->number, $order->status->label(), $order->payment_status->label(),
                Taka::toInput($row['charged']), Taka::toInput($row['paid']), Taka::toInput($row['refunded']), Taka::toInput($row['balance']),
            ];
        }
        $lines[] = ['', 'Total', '', '', Taka::toInput($statement['totals']['charged']), Taka::toInput($statement['totals']['paid']), Taka::toInput($statement['totals']['refunded']), Taka::toInput($statement['totals']['balance'])];

        return $lines;
    }
}
