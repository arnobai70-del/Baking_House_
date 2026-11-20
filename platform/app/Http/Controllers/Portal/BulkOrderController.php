<?php

namespace App\Http\Controllers\Portal;

use App\Services\Cart\CartService;
use App\Services\Portal\BulkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bulk order: paste "SKU, quantity" lines or upload a CSV, check every line against this
 * organization's prices and stock, then add the valid lines to the cart.
 */
class BulkOrderController extends PortalController
{
    public function __construct(private BulkOrderService $bulk) {}

    public function show(Request $request): View
    {
        return $this->page($request, 'portal.bulk', 'bulk', ['lines' => old('lines', ''), 'results' => null]);
    }

    public function submit(Request $request, CartService $carts): View|RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:200'],
            'action' => ['required', 'in:check,add'],
        ]);

        $text = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) ($validated['lines'] ?? '');
        if (! mb_check_encoding($text, 'UTF-8')) {
            return back()->withErrors(['lines' => __('The file must be UTF-8 text.')]);
        }

        $context = $this->context($request);
        $results = $this->bulk->check($text, $context);

        if ($validated['action'] === 'add') {
            $this->requireBuyer($request);
            $outcome = $this->bulk->addToCart($carts->forUser($request->user()), $results, $context);
            $refused = count(array_filter($results, fn (array $row) => ! $row['ok']));

            return redirect()->route('cart.show')
                ->with('status', __(':count product(s) added to your cart.', ['count' => $outcome['added']]).($refused > 0 ? ' '.__(':count line(s) were not added; fix them and try again.', ['count' => $refused]) : ''))
                ->with('bulk_skipped', $outcome['failed']);
        }

        return $this->page($request, 'portal.bulk', 'bulk', ['lines' => $text, 'results' => $results]);
    }
}
