<?php

namespace App\Http\Controllers\Portal;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\Cart\CartService;
use App\Services\Quotations\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Business quotations: the organization's RFQs and issued quotes. Owners and buyers accept a
 * quote (it goes to the cart at exactly the quoted lines and total) or decline it; viewers only
 * read. Quotation records are never visible to reseller accounts or other organizations.
 */
class QuotationController extends PortalController
{
    public function __construct(private QuotationService $quotations) {}

    public function index(Request $request): View
    {
        return $this->page($request, 'portal.quotations.index', 'quotations', [
            'quotations' => $this->organization($request)->quotations()->paginate(20),
            'prefill' => ['name' => $request->user()->name, 'phone' => $request->user()->phone, 'email' => $request->user()->email],
        ]);
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->own($request, $quotation);

        return $this->page($request, 'portal.quotations.show', 'quotations', ['quotation' => $quotation->load('items')]);
    }

    public function accept(Request $request, Quotation $quotation, CartService $carts): RedirectResponse
    {
        $this->own($request, $quotation);
        $this->requireBuyer($request);

        if ($quotation->status === QuotationStatus::Quoted) {
            $this->quotations->accept($quotation, $request->user(), $this->organization($request));
        }

        $carts->addQuotation($carts->forUser($request->user()), $quotation->refresh(), $this->context($request));

        return redirect()->route('cart.show')->with('status', __('Quotation :number accepted and added to your cart. Complete checkout before :date.', [
            'number' => $quotation->number, 'date' => $quotation->valid_until?->timezone('Asia/Dhaka')->format('d M Y'),
        ]));
    }

    public function decline(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->own($request, $quotation);
        $this->requireBuyer($request);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->quotations->decline($quotation, $request->user(), $this->organization($request), $validated['reason'] ?? null);

        return back()->with('status', __('Quotation declined.'));
    }

    private function own(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->organization_id === $this->organization($request)->id, 404);
    }
}
