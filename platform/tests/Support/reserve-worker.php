<?php

/**
 * Concurrency test worker (tests/Feature/ConcurrentReservationTest.php). Runs as its own PHP
 * process against a shared SQLite file, waits for a start barrier so all workers race at once,
 * then attempts one reservation and prints OK / SHORT / ERR.
 *
 * Usage: php reserve-worker.php <db-file> <variant-id> <quantity> <reference> <barrier-file>
 */

use App\Exceptions\InsufficientStockException;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $database, $variantId, $quantity, $reference, $barrier] = $argv;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'concurrency',
    'database.connections.concurrency' => [
        'driver' => 'sqlite',
        'database' => $database,
        'foreign_key_constraints' => true,
        'busy_timeout' => 20000,
        'journal_mode' => 'wal',
    ],
]);
DB::purge('concurrency');

$deadline = microtime(true) + 30;
while (! file_exists($barrier) && microtime(true) < $deadline) {
    usleep(500);
}

try {
    app(InventoryService::class)->reserve((int) $variantId, (int) $quantity, $reference);
    echo 'OK';
} catch (InsufficientStockException) {
    echo 'SHORT';
} catch (Throwable $e) {
    echo 'ERR '.get_class($e).': '.$e->getMessage();
}
