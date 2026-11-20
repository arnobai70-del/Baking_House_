<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuotationStatus;
use App\Http\Controllers\Portal\PortalController as BasePortalController;
use App\Models\Order;
use App\Models\Quotation;
use App\Services\Cart\CartService;
use App\Services\Portal\BulkOrderService;
use App\Services\Portal\RateCard;
use App\Services\Quotations\QuotationService;
use App\Support\BangladeshPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * /api/v1/business/* and /api/v1/reseller/*: the portal over the API.
 *
 * Same services and the same isolation as the web portal: the organization comes from
 * EnsureOrganizationAccess (X-Organization-Id is strictly validated), read endpoints need the
 * {type}:read ability, writes need {type}:write AND a member role that may order.
 */
class PortalController extends BasePortalController
{
    public function orders(Request $request): JsonResponse
    {
        $orders = $this->organization($request)->orders()->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => [
                'number' => $order->number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'total_paisa' => $order->total_paisa,
                'placed_at' => $order->placed_at->toAtomString(),
                'placed_by_you' => $order->user_id === $request->user()->id,
            ])->values(),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function prices(Request $request, RateCard $rateCard): JsonResponse
    {
        return response()->json(['data' => array_map(fn (array $row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'standard_paisa' => $row['standard_paisa'],
            'tiers' => $row['tiers'],
        ], $rateCard->rows($this->context($request), $request->query('q')))]);
    }

    public function bulkCheck(Request $request, BulkOrderService $bulk): JsonResponse
    {
        return response()->json(['data' => $this->serializeBulk($bulk->check($this->bulkText($request), $this->context($request)))]);
    }

    public function bulkAdd(Request $request, BulkOrderService $bulk, CartService $carts): JsonResponse
    {
        $this->requireBuyer($request);
        $context = $this->context($request);
        $checked = $bulk->check($this->bulkText($request), $context);
        $outcome = $bulk->addToCart($carts->forUser($request->user()), $checked, $context);

        return response()->json(['added' => $outcome['added'], 'failed' => $outcome['failed'], 'lines' => $this->serializeBulk($checked)]);
    }

    public function quotations(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->organization($request)->quotations()->with('items')->limit(100)->get()->map(fn (Quotation $quotation) => $this->serializeQuotation($quotation))->values()]);
    }

    public function quotation(Request $request, string $number): JsonResponse
    {
        return response()->json(['data' => $this->serializeQuotation($this->ownQuotation($request, $number)->load('items'))]);
    }

    public function requestQuotation(Request $request, QuotationService $quotations): JsonResponse
    {
        $this->requireBuyer($request);
        $validated = $request->validate([
            'request_text' => ['required', 'string', 'min:3', 'max:5000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $phone = BangladeshPhone::normalize($validated['contact_phone'] ?? $user->phone);
        if ($phone === null) {
            throw ValidationException::withMessages(['contact_phone' => __('Enter a valid Bangladesh mobile number (01XXXXXXXXX).')]);
        }

        $quotation = $quotations->request([
            'contact_name' => $user->name,
            'contact_phone' => $phone,
            'contact_email' => $user->email,
            'request_text' => $validated['request_text'],
            'customer_note' => $validated['customer_note'] ?? null,
        ], $user, $this->organization($request));

        return response()->json(['data' => $this->serializeQuotation($quotation->load('items'))], 201);
    }

    public function acceptQuotation(Request $request, string $number, QuotationService $quotations, CartService $carts): JsonResponse
    {
        $quotation = $this->ownQuotation($request, $number);
        $this->requireBuyer($request);

        if ($quotation->status === QuotationStatus::Quoted) {
            $quotations->accept($quotation, $request->user(), $this->organization($request));
        }
        $carts->addQuotation($carts->forUser($request->user()), $quotation->refresh(), $this->context($request));

        return response()->json(['data' => $this->serializeQuotation($quotation->load('items')), 'in_cart' => true]);
    }

    private function ownQuotation(Request $request, string $number): Quotation
    {
        $quotation = Quotation::query()->where('number', strtoupper($number))->first();
        abort_unless($quotation !== null && $quotation->organization_id === $this->organization($request)->id, 404);

        return $quotation;
    }

    /**
     * Accepts either {"lines": [{"sku": "...", "quantity": "6kg"}, ...]} or {"text": "SKU, qty\n..."}.
     */
    private function bulkText(Request $request): string
    {
        $validated = $request->validate([
            'text' => ['required_without:lines', 'nullable', 'string', 'max:20000'],
            'lines' => ['required_without:text', 'nullable', 'array', 'max:'.BulkOrderService::MAX_LINES],
            'lines.*.sku' => ['required', 'string', 'max:64'],
            'lines.*.quantity' => ['required', 'max:20'],
        ]);

        if (isset($validated['lines'])) {
            return implode("\n", array_map(fn (array $line) => str_replace([',', "\n", "\r"], ' ', (string) $line['sku']).','.str_replace([',', "\n", "\r"], ' ', (string) $line['quantity']), $validated['lines']));
        }

        return (string) $validated['text'];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function serializeBulk(array $rows): array
    {
        return array_map(fn (array $row) => [
            'line' => $row['line'], 'sku' => $row['sku'], 'name' => $row['name'], 'ok' => $row['ok'], 'error' => $row['error'],
            'quantity' => $row['quantity'], 'quantity_label' => $row['quantity_label'], 'line_paisa' => $row['line_paisa'], 'rate_source' => $row['rate_source'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeQuotation(Quotation $quotation): array
    {
        return [
            'number' => $quotation->number,
            'status' => $quotation->status->value,
            'request_text' => $quotation->request_text,
            'total_paisa' => $quotation->total_paisa,
            'valid_until' => $quotation->valid_until?->toAtomString(),
            'terms' => $quotation->terms,
            'items' => $quotation->items->map(fn ($item) => [
                'sku' => $item->sku, 'description' => $item->description, 'quantity' => $item->quantity,
                'quantity_label' => $item->quantity_label, 'line_paisa' => $item->line_paisa,
            ])->values(),
            'order_number' => $quotation->order_id === null ? null : $quotation->order()->value('number'),
            'created_at' => $quotation->created_at->toAtomString(),
        ];
    }
}
