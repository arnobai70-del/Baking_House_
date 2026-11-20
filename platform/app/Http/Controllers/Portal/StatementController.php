<?php

namespace App\Http\Controllers\Portal;

use App\Services\Portal\StatementService;
use App\Support\CsvDownload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monthly statements (orders, paid, refunded, balance) with CSV export, plus invoice links.
 */
class StatementController extends PortalController
{
    public function __invoke(Request $request, StatementService $statements): View|StreamedResponse
    {
        $input = (string) $request->query('month', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $input) === 1
            ? CarbonImmutable::createFromFormat('!Y-m', $input, 'Asia/Dhaka')
            : CarbonImmutable::now('Asia/Dhaka');

        $statement = $statements->forMonth($this->organization($request), $month);

        if ($request->query('format') === 'csv') {
            return CsvDownload::response(
                'statement-'.Str::slug($this->organization($request)->name).'-'.$statement['month']->format('Y-m').'.csv',
                $statements->csv($statement),
            );
        }

        return $this->page($request, 'portal.statements', 'statements', [
            'statement' => $statement,
            'previous' => $statement['month']->subMonth()->format('Y-m'),
            'next' => $statement['month']->addMonth()->isAfter(now('Asia/Dhaka')) ? null : $statement['month']->addMonth()->format('Y-m'),
        ]);
    }
}
