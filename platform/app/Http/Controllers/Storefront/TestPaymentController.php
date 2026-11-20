<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TestPaymentGateway;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * TEST MODE ONLY — a simulated hosted payment page for bKash / Nagad / card. It collects no card
 * or wallet details. The chosen outcome is delivered as a signed callback through the same
 * verification path as real provider webhooks; the customer is then returned to the order page,
 * which shows whatever the SERVER recorded (the redirect itself never marks anything paid).
 */
class TestPaymentController extends Controller
{
    public function show(string $reference): View
    {
        abort_unless(config('commerce.payments.mode') === 'test', 404);
        $payment = Payment::query()->where('reference', $reference)->with('order:id,number,total_paisa')->firstOrFail();

        return view('storefront.payments.test', ['seo' => (new Seo(title: 'Test payment'))->noindex(), 'payment' => $payment]);
    }

    public function complete(Request $request, string $reference, PaymentGatewayFactory $gateways, PaymentService $payments): RedirectResponse
    {
        abort_unless(config('commerce.payments.mode') === 'test', 404);
        $validated = $request->validate(['outcome' => ['required', Rule::in(['success', 'fail', 'cancel'])]]);
        $payment = Payment::query()->where('reference', $reference)->with('order')->firstOrFail();

        /** @var TestPaymentGateway $gateway */
        $gateway = $gateways->for($payment->provider);
        $signed = $gateway->signedCallback([
            'event_id' => 'evt_'.Str::lower(Str::random(20)),
            'type' => match ($validated['outcome']) {
                'success' => 'payment.succeeded',
                'fail' => 'payment.failed',
                default => 'payment.cancelled',
            },
            'reference' => $payment->reference,
            'transaction_id' => 'TEST-'.Str::upper(Str::random(10)),
            'amount_paisa' => $payment->amount_paisa,
            'currency' => $payment->currency,
        ]);

        $payments->handleCallback($payment->provider->value, $signed['body'], $signed['headers']);

        return redirect()->route('orders.show', $payment->order);
    }
}
