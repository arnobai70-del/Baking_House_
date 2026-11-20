<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Crm\CrmService;
use App\Support\BangladeshPhone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Contact & support (/contact). Messages become CRM leads in the shared queue so staff follow up;
 * the published "contact-information" page (owner-supplied) is shown above the form.
 */
class ContactController extends Controller
{
    public const TOPICS = ['order' => 'An existing order', 'product' => 'Products, stock or prices', 'business' => 'Business or reseller account', 'other' => 'Something else'];

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('storefront.contact', [
            'seo' => new Seo(title: __('Contact & support'), description: __('Ask about an order, a product or a business account. Our team replies by phone or email.'), canonical: route('contact')),
            'information' => Page::query()->where('slug', 'contact-information')->where('is_published', true)->first(),
            'topics' => self::TOPICS,
            'prefill' => ['name' => $user?->name, 'phone' => $user?->phone, 'email' => $user?->email],
        ]);
    }

    public function store(Request $request, CrmService $crm): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('status', __('Thank you. Our team will contact you.'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'topic' => ['required', Rule::in(array_keys(self::TOPICS))],
            'order_number' => ['nullable', 'string', 'max:24'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
        ]);
        $phone = BangladeshPhone::normalize($validated['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid Bangladesh mobile number (01XXXXXXXXX).')]);
        }

        $crm->captureContact([
            'name' => $validated['name'], 'phone' => $phone, 'email' => $validated['email'] ?? null,
            'topic' => self::TOPICS[$validated['topic']], 'order_number' => $validated['order_number'] ?? null, 'message' => $validated['message'],
        ], $request->user());

        return redirect()->route('contact')->with('status', __('Thank you. Your message has reached our team; we will reply soon.'));
    }
}
