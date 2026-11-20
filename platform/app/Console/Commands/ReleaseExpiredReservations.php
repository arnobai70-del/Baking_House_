<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:release-expired')]
#[Description('Release stock held by reservations whose hold time has passed')]
class ReleaseExpiredReservations extends Command
{
    public function handle(InventoryService $inventory): int
    {
        $count = $inventory->releaseExpired();
        $this->info("Released {$count} expired reservation(s).");

        return self::SUCCESS;
    }
}
