<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteRequest;
use App\Services\Pricing\PricingContextResolver;
use App\Services\Pricing\QuoteService;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/pricing/quote — server-authoritative basket price.
 * Guests get retail prices. With a Sanctum token, approved business / reseller members get their
 * own price lists (X-Organization-Id is strictly validated); nobody can request another audience.
 */
class PricingController extends Controller
{
    public function __invoke(QuoteRequest $request, PricingContextResolver $contexts, QuoteService $quotes): JsonResponse
    {
        $context = $contexts->forApi($request);

        return response()->json($quotes->quote(
            $request->validated('items', []),
            $request->validated('bundles', []),
            $context,
            $request->validated('coupon'),
        ));
    }
}
