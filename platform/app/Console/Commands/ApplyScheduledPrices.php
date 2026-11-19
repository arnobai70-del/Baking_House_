<?php

namespace App\Console\Commands;

use App\Services\Pricing\ScheduledPriceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pricing:apply-scheduled')]
#[Description('Apply scheduled price changes whose effective time has arrived')]
class ApplyScheduledPrices extends Command
{
    public function handle(ScheduledPriceService $service): int
    {
        $count = $service->applyDue();
        $this->info("Applied {$count} scheduled price change(s).");

        return self::SUCCESS;
    }
}
