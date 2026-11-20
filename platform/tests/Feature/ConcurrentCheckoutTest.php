<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * REAL concurrency at checkout: separate PHP processes submit orders at the same moment against a
 * shared database file. Proves no overselling and exactly-once order creation.
 */
class ConcurrentCheckoutTest extends TestCase
{
    private string $database;

    private string $barrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = storage_path('framework/testing/checkout-race-'.getmypid().'.sqlite');
        $this->barrier = $this->database.'.go';
        @mkdir(dirname($this->database), 0777, true);
        $this->cleanup();
        touch($this->database);

        config([
            'database.default' => 'concurrency',
            'database.connections.concurrency' => [
                'driver' => 'sqlite', 'database' => $this->database, 'foreign_key_constraints' => true, 'busy_timeout' => 20000, 'journal_mode' => 'wal',
            ],
            'mail.default' => 'array',
        ]);
        DB::purge('concurrency');
        Artisan::call('migrate', ['--database' => 'concurrency', '--force' => true]);
        DeliveryZone::query()->create(['name' => 'Dhaka', 'districts' => ['Dhaka'], 'is_default' => true, 'charge_paisa' => 8000, 'cod_available' => true, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('concurrency');
        $this->cleanup();
        parent::tearDown();
    }

    public function test_competing_checkouts_never_oversell(): void
    {
        $variant = Product::factory()->withFixedVariant(100000, 10, ['sku' => 'RACE-PAN'])->create()->variants()->sole();
        $carts = [];
        for ($i = 0; $i < 8; $i++) {
            $cart = Cart::query()->create(['token' => Str::random(40)]);
            $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 3]);
            $carts[] = $cart->id;
        }
        DB::disconnect('concurrency');

        $results = $this->race(array_map(fn (int $cartId) => [(string) $cartId, Str::random(40)], $carts));

        $placed = array_filter($results, fn (string $r) => str_starts_with($r, 'OK '));
        $errors = array_filter($results, fn (string $r) => str_starts_with($r, 'ERR'));
        $this->assertSame([], array_values($errors), implode(' | ', $errors));
        $this->assertCount(3, $placed, 'Only floor(10 / 3) = 3 orders can be placed.');
        $this->assertSame(3, Order::query()->count());
        $this->assertSame(1, $variant->fresh()->stock_quantity);
        $this->assertSame(0, $variant->fresh()->reserved_quantity);
        $this->assertSame([], app(InventoryService::class)->discrepancies());
    }

    public function test_simultaneous_duplicate_submits_create_one_order(): void
    {
        $variant = Product::factory()->withFixedVariant(100000, 10, ['sku' => 'RACE-DUP'])->create()->variants()->sole();
        $cart = Cart::query()->create(['token' => Str::random(40)]);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 2]);
        DB::disconnect('concurrency');

        $token = Str::random(40);
        $results = $this->race(array_fill(0, 6, [(string) $cart->id, $token]));

        $errors = array_filter($results, fn (string $r) => str_starts_with($r, 'ERR'));
        $this->assertSame([], array_values($errors), implode(' | ', $errors));
        $numbers = array_unique(array_map(fn (string $r) => substr($r, 3), array_filter($results, fn (string $r) => str_starts_with($r, 'OK '))));
        $this->assertCount(1, $numbers, 'Every successful submit refers to the same order.');
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(8, $variant->fresh()->stock_quantity, 'Stock taken exactly once.');
    }

    /**
     * @param  list<array{0: string, 1: string}>  $jobs  [cart id, checkout token]
     * @return list<string>
     */
    private function race(array $jobs): array
    {
        $processes = [];
        foreach ($jobs as [$cartId, $token]) {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/checkout-worker.php'), $this->database, $cartId, $token, $this->barrier], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($process);
            $processes[] = [$process, $pipes];
        }

        usleep(2500000);
        touch($this->barrier);

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $out = trim((string) stream_get_contents($pipes[1]));
            $err = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $results[] = $out !== '' ? $out : 'ERR '.$err;
        }
        DB::purge('concurrency');

        return $results;
    }

    private function cleanup(): void
    {
        foreach ([$this->database, $this->database.'-wal', $this->database.'-shm', $this->barrier] as $file) {
            @unlink($file);
        }
    }
}
