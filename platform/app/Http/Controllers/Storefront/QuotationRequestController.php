<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Services\OrganizationContext;
use App\Services\Quotations\QuotationService;
use App\Support\BangladeshPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /quotations — the one RFQ entry point (product page, /business landing, business portal).
 *
 * A signed-in owner / buyer of an approved business organization files it for that organization
 * (it then appears in the portal and can be quoted and accepted online). Everyone else creates a
 * sales lead that staff follow up by phone. A hidden honeypot field drops naive bots.
 */
class QuotationRequestController extends Controller
{
    public function __invoke(Request $request, QuotationService $quotations): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('status', __('Thank you. Our team will contact you.'));
        }

        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'request_text' => ['required', 'string', 'min:3', 'max:5000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $phone = BangladeshPhone::normalize($validated['contact_phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['contact_phone' => __('Enter a valid Bangladesh mobile number (01XXXXXXXXX).')]);
        }

        $user = $request->user();
        $organization = $user === null ? null : (new OrganizationContext($request->session()))->resolveFromSession($user, OrganizationType::Business);
        if ($organization !== null && ! ($user->roleIn($organization)?->canPlaceOrders() ?? false)) {
            $organization = null;
        }

        $quotation = $quotations->request([
            'contact_name' => $validated['contact_name'],
            'contact_phone' => $phone,
            'contact_email' => $validated['contact_email'] ?? null,
            'request_text' => $validated['request_text'],
            'customer_note' => $validated['customer_note'] ?? null,
        ], $user, $organization);

        if ($organization !== null) {
            return redirect()->route('business.quotations.show', $quotation)
                ->with('status', __('Quotation request :number sent. We will notify you when the quote is ready.', ['number' => $quotation->number]));
        }

        return back()->with('status', __('Quotation request :number received. Our sales team will contact you.', ['number' => $quotation->number]));
    }
}
