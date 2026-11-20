<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Services\Quotations\QuotationService;
use App\Support\Taka;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff side of RFQs: review requests, issue / revise quotes (fixed quantity + price per line,
 * validity date), and close leads. Everything goes through QuotationService (guarded, audited).
 */
class QuotationController extends Controller
{
    public function __construct(private QuotationService $quotations) {}

    public function index(Request $request): View
    {
        $status = QuotationStatus::tryFrom((string) $request->query('status', ''));

        return view('admin.quotations.index', [
            'quotations' => Quotation::query()
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->with('organization:id,name,type')
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'counts' => Quotation::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items.variant', 'organization:id,name,type,status,contact_phone', 'requester:id,name,email,phone', 'order:id,number']);

        $rows = $quotation->items->map(fn ($item) => [
            'sku' => $item->sku, 'description' => $item->description,
            'quantity' => $item->sku !== null && str_ends_with($item->quantity_label, 'g') ? $item->quantity.'g' : (string) $item->quantity,
            'price' => Taka::toInput($item->line_paisa),
        ])->all();

        return view('admin.quotations.show', [
            'quotation' => $quotation,
            'rows' => old('lines', array_pad($rows, max(count($rows) + 2, 4), ['sku' => '', 'description' => '', 'quantity' => '', 'price' => ''])),
            'validUntil' => old('valid_until', ($quotation->valid_until ?? now()->addDays(QuotationService::DEFAULT_VALID_DAYS))->timezone('Asia/Dhaka')->format('Y-m-d')),
        ]);
    }

    public function issue(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'max:50'],
            'lines.*.sku' => ['nullable', 'string', 'max:64'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'string', 'max:20'],
            'lines.*.price' => ['nullable', 'string', 'max:20'],
            'valid_until' => ['required', 'date_format:Y-m-d'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $validUntil = CarbonImmutable::createFromFormat('Y-m-d', $validated['valid_until'], 'Asia/Dhaka')->endOfDay()->utc();
        if ($validUntil->isAfter(now()->addDays(90))) {
            throw ValidationException::withMessages(['valid_until' => __('A quote can be valid for at most 90 days.')]);
        }

        $lines = $this->quotations->parseLines(array_values($validated['lines']));
        $this->quotations->issue($quotation, $lines, $validUntil, $validated['terms'] ?? null, $validated['internal_note'] ?? null, $request->user());

        return redirect()->route('admin.quotations.show', $quotation)->with('status', __('Quote issued to the customer.'));
    }

    public function close(Request $request, Quotation $quotation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $this->quotations->close($quotation, $request->user(), $validated['reason']);

        return redirect()->route('admin.quotations.show', $quotation)->with('status', __('Quotation closed.'));
    }
}
