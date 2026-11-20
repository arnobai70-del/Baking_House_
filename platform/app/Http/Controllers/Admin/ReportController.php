<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use App\Support\CsvDownload;
use App\Support\Taka;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analytics & reports (reports.view). Money figures (sales, discounts, refunds, revenue, spend)
 * additionally need reports.financial; without it the same pages show counts and quantities only.
 * Every section can be downloaded as CSV with ?format=csv.
 */
class ReportController extends Controller
{
    public const SECTIONS = ['overview', 'products', 'inventory', 'promotions', 'customers', 'rfq'];

    public function __construct(private ReportService $reports) {}

    public function __invoke(Request $request, string $section = 'overview'): View|StreamedResponse
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $money = $request->user()->can(StaffPermission::ViewFinancialReports->value);
        abort_if($section === 'promotions' && ! $money, 403);
        [$from, $to, $preset] = $this->range($request);

        $data = match ($section) {
            'overview' => [
                'financial' => $money ? $this->reports->financial($from, $to) : null,
                'daily' => $this->reports->daily($from, $to),
                'statuses' => $this->reports->byStatus($from, $to),
                'payments' => $this->reports->byPaymentStatus($from, $to),
                'audiences' => $this->reports->byAudience($from, $to),
            ],
            'products' => ['products' => $this->reports->topProducts($from, $to, 25), 'categories' => $this->reports->topCategories($from, $to, 15)],
            'inventory' => ['inventory' => $this->reports->inventory()],
            'promotions' => ['promotions' => $this->reports->promotions($from, $to)],
            'customers' => ['customers' => $this->reports->customers($from, $to)],
            'rfq' => ['rfq' => $this->reports->rfq($from, $to)],
        };

        if ($request->query('format') === 'csv') {
            return CsvDownload::response("report-{$section}-{$from->toDateString()}-to-{$to->toDateString()}.csv", $this->csv($section, $data, $money));
        }

        return view('admin.reports.'.$section, $data + [
            'section' => $section, 'from' => $from, 'to' => $to, 'preset' => $preset, 'money' => $money,
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $preset = (string) $request->query('range', '30d');

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            '7d' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'custom' => [$this->date($request->query('from')) ?? $today->subDays(29), $this->date($request->query('to')) ?? $today],
            default => [$today->subDays(29), $today],
        };
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        return [$from, $to, in_array($preset, ['today', '7d', 'month', 'last_month', 'custom'], true) ? $preset : '30d'];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Dhaka') : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<list<string|int|null>>
     */
    private function csv(string $section, array $data, bool $money): array
    {
        $taka = fn (int $paisa) => Taka::toInput($paisa);

        return match ($section) {
            'overview' => array_merge(
                $money ? [['Measure', 'BDT'], ...array_map(fn ($key, $value) => [str_replace('_', ' ', $key), in_array($key, ['orders', 'cancelled_orders'], true) ? $value : $taka($value)], array_keys($data['financial']), $data['financial']), []] : [],
                [['Date', 'Orders', ...($money ? ['Billed (BDT)'] : [])]],
                array_map(fn ($day) => [$day['date'], $day['orders'], ...($money ? [$taka($day['billed'])] : [])], $data['daily']),
                [[], ['Order status', 'Orders', ...($money ? ['Total (BDT)'] : [])]],
                array_map(fn ($row) => [$row['status']->label(), $row['orders'], ...($money ? [$taka($row['total'])] : [])], $data['statuses']),
                [[], ['Customer type', 'Orders', ...($money ? ['Gross (BDT)', 'Discounts (BDT)', 'Billed (BDT)'] : [])]],
                array_map(fn ($row) => [$row['audience'], $row['orders'], ...($money ? [$taka($row['gross']), $taka($row['discounts']), $taka($row['billed'])] : [])], $data['audiences']),
            ),
            'products' => array_merge(
                [['SKU', 'Product', 'Quantity', 'Base units', 'Orders', ...($money ? ['Revenue (BDT)'] : [])]],
                array_map(fn ($row) => [$row['sku'], $row['name'], $row['quantity_label'], $row['quantity'], $row['orders'], ...($money ? [$taka($row['revenue'])] : [])], $data['products']),
                [[], ['Category', 'Orders', ...($money ? ['Revenue (BDT)'] : [])]],
                array_map(fn ($row) => [$row['category'], $row['orders'], ...($money ? [$taka($row['revenue'])] : [])], $data['categories']),
            ),
            'inventory' => array_merge(
                [['Status', 'SKU', 'Product', 'Available (base units)', 'Low-stock threshold']],
                array_map(fn ($variant) => ['Out of stock', $variant->sku, $variant->product->name, $variant->availableQuantity(), $variant->low_stock_threshold], $data['inventory']['out']),
                array_map(fn ($variant) => ['Low stock', $variant->sku, $variant->product->name, $variant->availableQuantity(), $variant->low_stock_threshold], $data['inventory']['low']),
            ),
            'promotions' => array_merge(
                [['Promotion', 'Code', 'Uses', 'Discount given (BDT)', 'Billed on those orders (BDT)']],
                array_map(fn ($row) => [$row['name'], $row['code'], $row['uses'], $taka($row['discount']), $taka($row['billed'])], $data['promotions']),
            ),
            'customers' => [['Measure', 'Value'], ...array_map(fn ($key, $value) => [str_replace('_', ' ', $key), $value ?? ''], array_keys($data['customers']), $data['customers'])],
            'rfq' => array_merge(
                [['Measure', 'Value']],
                array_map(fn ($key) => [str_replace('_', ' ', $key), str_ends_with($key, '_value') ? ($money ? $taka($data['rfq'][$key]) : '') : ($data['rfq'][$key] ?? '')],
                    ['requested', 'quoted', 'accepted', 'ordered', 'lost', 'conversion', 'quoted_value', 'ordered_value', 'queue']),
                [[], ['Pipeline stage', 'Leads', ...($money ? ['Estimated value (BDT)'] : [])]],
                array_map(fn ($row) => [$row['stage']->label(), $row['leads'], ...($money ? [$taka($row['value'])] : [])], $data['rfq']['pipeline']),
            ),
        };
    }
}
