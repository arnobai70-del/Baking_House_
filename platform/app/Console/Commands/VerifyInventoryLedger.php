<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:verify')]
#[Description('Check that stock on hand matches the ledger and reserved stock matches active reservations')]
class VerifyInventoryLedger extends Command
{
    public function handle(InventoryService $inventory): int
    {
        $problems = $inventory->discrepancies();

        if ($problems === []) {
            $this->info('Inventory ledger reconciles for every variant.');

            return self::SUCCESS;
        }

        $this->error(count($problems).' variant(s) do not reconcile:');
        $this->table(array_keys($problems[0]), $problems);

        return self::FAILURE;
    }
}
