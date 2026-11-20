<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-unpaid')]
#[Description('Cancel unpaid online orders past their grace period and unpaid bank transfers past their window (stock is restored)')]
class ExpireUnpaidOrders extends Command
{
    public function handle(OrderService $orders): int
    {
        $count = $orders->expireUnpaid();
        $this->info("Cancelled {$count} unpaid order(s).");

        return self::SUCCESS;
    }
}
