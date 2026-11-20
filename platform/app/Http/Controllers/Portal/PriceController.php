<?php

namespace App\Http\Controllers\Portal;

use App\Services\Portal\RateCard;
use App\Support\CsvDownload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The organization's confidential price list / reseller rate card, and its CSV export
 * (reseller catalog tool). Prices are resolved on the server for this organization only.
 */
class PriceController extends PortalController
{
    public function __construct(private RateCard $rateCard) {}

    public function index(Request $request): View
    {
        $search = (string) $request->query('q', '');

        return $this->page($request, 'portal.prices', 'prices', [
            'rows' => $this->rateCard->rows($this->context($request), $search),
            'search' => $search,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);

        return CsvDownload::response(
            'baking-house-prices-'.Str::slug($organization->name).'-'.now('Asia/Dhaka')->format('Y-m-d').'.csv',
            $this->rateCard->csv($this->context($request)),
        );
    }
}
