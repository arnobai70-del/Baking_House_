<?php

namespace App\Console\Commands;

use App\Services\Quotations\QuotationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotations:expire')]
#[Description('Mark quoted / accepted quotations past their validity date as expired')]
class ExpireQuotations extends Command
{
    public function handle(QuotationService $quotations): int
    {
        $count = $quotations->expireStale();
        $this->info("Expired {$count} quotation(s).");

        return self::SUCCESS;
    }
}
