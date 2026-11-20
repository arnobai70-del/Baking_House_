<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\StockReservation;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * REAL concurrency: separate PHP processes race for the same stock on a shared database file.
 * Proves the guarded UPDATE prevents overselling (blueprint §05 "concurrent carts compete for inventory").
 */
class ConcurrentReservationTest extends TestCase
{
    private string $database;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = storage_path('framework/testing/concurrency-'.getmypid().'.sqlite');
        $this->barrier = $this->database.'.go';
        @mkdir(dirname($this->database), 0777, true);
        foreach ([$this->database, $this->database.'-wal', $this->database.'-shm', $this->barrier] as $file) {
            @unlink($file);
        }
        touch($this->database);

        config([
            'database.default' => 'concurrency',
            'database.connections.concurrency' => [
                'driver' => 'sqlite',
                'database' => $this->database,
                'foreign_key_constraints' => true,
                'busy_timeout' => 20000,
                'journal_mode' => 'wal',
            ],
        ]);
        DB::purge('concurrency');
        Artisan::call('migrate', ['--database' => 'concurrency', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('concurrency');
        foreach ([$this->database, $this->database.'-wal', $this->database.'-shm', $this->barrier] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_parallel_reservations_never_oversell(): void
    {
        $variant = Product::factory()->withFixedVariant(245000, 10, ['sku' => 'RACE-1'])->create()->variants()->sole();
        DB::disconnect('concurrency');

        $workers = 12;
        $results = $this->race($workers, fn (int $i) => [(string) $variant->id, '3', "cart-{$i}"]);

        $ok = count(array_filter($results, fn (string $r) => $r === 'OK'));
        $short = count(array_filter($results, fn (string $r) => $r === 'SHORT'));
        $errors = array_values(array_filter($results, fn (string $r) => ! in_array($r, ['OK', 'SHORT'], true)));

        $this->assertSame([], $errors, 'Workers must not crash: '.implode(' | ', $errors));
        $this->assertSame(3, $ok, 'Exactly floor(10 / 3) = 3 carts can win.');
        $this->assertSame($workers - 3, $short);

        $fresh = $variant->fresh();
        $this->assertSame(9, $fresh->reserved_quantity);
        $this->assertSame(10, $fresh->stock_quantity);
        $this->assertSame(3, StockReservation::query()->where('status', ReservationStatus::Active->value)->count());
        $this->assertSame([], app(InventoryService::class)->discrepancies());
    }

    public function test_parallel_weight_reservations_never_oversell_grams(): void
    {
        $variant = Product::factory()->withWeightVariant(65000, 2000, ['sku' => 'RACE-G'])->create()->variants()->sole();
        DB::disconnect('concurrency');

        $results = $this->race(10, fn (int $i) => [(string) $variant->id, '700', "weight-{$i}"]);

        $this->assertSame(2, count(array_filter($results, fn (string $r) => $r === 'OK')), '2 × 700 g fit in 2 kg; a third would oversell.');
        $this->assertSame(1400, $variant->fresh()->reserved_quantity);
    }

    /**
     * Start all workers, release them together through a barrier file, collect their output.
     *
     * @param  callable(int): list<string>  $arguments
     * @return list<string>
     */
    private function race(int $workers, callable $arguments): array
    {
        $script = base_path('tests/Support/reserve-worker.php');
        $processes = [];

        for ($i = 0; $i < $workers; $i++) {
            $command = array_merge([PHP_BINARY, $script, $this->database], $arguments($i), [$this->barrier]);
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        usleep(1500000);
        touch($this->barrier);

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $output = trim((string) stream_get_contents($pipes[1]));
            $error = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $results[] = $output !== '' ? $output : 'ERR '.$error;
        }

        DB::purge('concurrency');

        return $results;
    }
}
