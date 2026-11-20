<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TestPaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/webhooks/payments/{provider} — provider callbacks. The raw body is verified
 * (signature, freshness) and processed idempotently by PaymentService.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        [$status, $result] = $payments->handleCallback($provider, $request->getContent(), [
            TestPaymentGateway::SIGNATURE_HEADER => $request->header(TestPaymentGateway::SIGNATURE_HEADER),
            TestPaymentGateway::TIMESTAMP_HEADER => $request->header(TestPaymentGateway::TIMESTAMP_HEADER),
        ]);

        return response()->json(['result' => $result], $status);
    }
}
