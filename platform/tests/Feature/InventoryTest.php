<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\AuditLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private ProductVariant $pan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->pan = Product::factory()->withFixedVariant(245000, 10, ['sku' => 'PAN', 'low_stock_threshold' => 3])->create()->variants()->sole();
    }

    public function test_new_stock_is_recorded_as_an_opening_ledger_entry(): void
    {
        $opening = InventoryMovement::query()->where('product_variant_id', $this->pan->id)->sole();

        $this->assertSame(InventoryMovementType::Opening, $opening->type);
        $this->assertSame(10, $opening->quantity);
        $this->assertSame([], $this->inventory->discrepancies());
    }

    public function test_reserve_holds_stock_and_blocks_overselling(): void
    {
        $this->inventory->reserve($this->pan, 7, 'checkout-A');

        $this->assertSame(3, $this->inventory->available($this->pan));
        $this->assertSame(10, $this->pan->fresh()->stock_quantity, 'On hand is unchanged until the sale is committed.');

        try {
            $this->inventory->reserve($this->pan, 4, 'checkout-B');
            $this->fail('Overselling must be rejected.');
        } catch (InsufficientStockException $e) {
            $this->assertSame(3, $e->available);
        }

        $this->assertSame(7, $this->pan->fresh()->reserved_quantity);
    }

    public function test_reserve_many_is_all_or_nothing(): void
    {
        $flour = Product::factory()->withWeightVariant(11500, 1000)->create()->variants()->sole();

        try {
            $this->inventory->reserveMany([[$this->pan, 2], [$flour, 5000]], 'bundle-checkout');
            $this->fail('Second line is short.');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(0, $this->pan->fresh()->reserved_quantity, 'First line rolled back.');
        $this->assertSame(0, $flour->fresh()->reserved_quantity);
        $this->assertDatabaseCount('stock_reservations', 0);
    }

    public function test_weight_reservations_use_grams(): void
    {
        $chocolate = Product::factory()->withWeightVariant(65000, 18450)->create()->variants()->sole();

        $this->inventory->reserve($chocolate, 700, 'c1');

        $this->assertSame(17750, $this->inventory->available($chocolate));
    }

    public function test_release_returns_stock_once_only(): void
    {
        $reservation = $this->inventory->reserve($this->pan, 4, 'checkout-A');

        $this->assertTrue($this->inventory->release($reservation));
        $this->assertFalse($this->inventory->release($reservation), 'A reservation can never be released twice.');

        $this->assertSame(0, $this->pan->fresh()->reserved_quantity);
        $this->assertSame(ReservationStatus::Released, $reservation->fresh()->status);
    }

    public function test_commit_turns_reservation_into_a_sale_ledger_entry(): void
    {
        $reservation = $this->inventory->reserve($this->pan, 4, 'order-1');

        $this->assertTrue($this->inventory->commit($reservation));
        $this->assertFalse($this->inventory->commit($reservation));
        $this->assertFalse($this->inventory->release($reservation), 'Committed stock cannot be released back.');

        $variant = $this->pan->fresh();
        $this->assertSame(6, $variant->stock_quantity);
        $this->assertSame(0, $variant->reserved_quantity);
        $sale = InventoryMovement::query()->where('type', 'sale')->sole();
        $this->assertSame(-4, $sale->quantity);
        $this->assertSame(6, $sale->balance_after);
        $this->assertSame([], $this->inventory->discrepancies());
    }

    public function test_expired_reservations_are_released_by_the_scheduler_command(): void
    {
        $this->inventory->reserve($this->pan, 5, 'abandoned', holdMinutes: 15);
        $this->inventory->reserve($this->pan, 2, 'fresh', holdMinutes: 60);

        $this->travel(20)->minutes();
        $this->artisan('inventory:release-expired')->expectsOutputToContain('Released 1')->assertSuccessful();

        $this->assertSame(2, $this->pan->fresh()->reserved_quantity);
        $this->assertSame(8, $this->inventory->available($this->pan));
    }

    public function test_stock_decrease_cannot_go_below_reserved(): void
    {
        $this->inventory->reserve($this->pan, 8, 'order-x');

        $this->expectException(InsufficientStockException::class);
        $this->inventory->record($this->pan, InventoryMovementType::Damage, -3, 'Dented');
    }

    public function test_receipt_and_adjustment_update_on_hand_with_ledger_balance(): void
    {
        $receipt = $this->inventory->record($this->pan, InventoryMovementType::Receipt, 20, 'PO-1001');
        $damage = $this->inventory->record($this->pan, InventoryMovementType::Damage, -2, 'Dented');

        $this->assertSame(30, $receipt->balance_after);
        $this->assertSame(28, $damage->balance_after);
        $this->assertSame(28, $this->pan->fresh()->stock_quantity);
        $this->assertSame([], $this->inventory->discrepancies());
    }

    public function test_reconciliation_sets_counted_quantity_and_logs_difference(): void
    {
        $movement = $this->inventory->reconcile($this->pan, 7, 'Monthly count');

        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, $this->pan->fresh()->stock_quantity);
        $this->assertSame(InventoryMovementType::Reconciliation, $movement->type);
    }

    public function test_reconciliation_below_reserved_is_rejected(): void
    {
        $this->inventory->reserve($this->pan, 6, 'order-y');

        $this->expectException(InsufficientStockException::class);
        $this->inventory->reconcile($this->pan, 5, 'Count');
    }

    public function test_verify_command_detects_tampering_outside_the_ledger(): void
    {
        $this->artisan('inventory:verify')->expectsOutputToContain('reconciles')->assertSuccessful();

        DB::table('product_variants')->where('id', $this->pan->id)->update(['stock_quantity' => 50]);

        $this->artisan('inventory:verify')->assertFailed();
        $this->assertSame('PAN', $this->inventory->discrepancies()[0]['sku']);
    }

    public function test_low_stock_alert_fires_once_and_resets(): void
    {
        $this->inventory->reserve($this->pan, 7, 'r1');
        $this->inventory->reserve($this->pan, 1, 'r2');

        $this->assertSame(1, AuditLog::query()->where('action', 'inventory.low_stock')->count());
        $this->assertNotNull($this->pan->fresh()->low_stock_alerted_at);

        $this->inventory->record($this->pan, InventoryMovementType::Receipt, 20, 'Restock');
        $this->assertNull($this->pan->fresh()->low_stock_alerted_at);
    }

    public function test_listing_flag_follows_available_stock(): void
    {
        $this->inventory->reserve($this->pan, 10, 'all');

        $this->assertFalse($this->pan->product()->first()->in_stock);
    }

    public function test_ledger_rows_are_immutable(): void
    {
        $movement = InventoryMovement::query()->first();

        $this->expectException(LogicException::class);
        $movement->update(['quantity' => 999]);
    }

    public function test_editing_a_product_never_changes_stock_outside_the_ledger(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $product = $this->pan->product()->first();

        $this->actingAsStaff($admin)->put("/admin/products/{$product->id}", [
            'name' => $product->name, 'status' => 'active', 'primary_category_id' => $product->primary_category_id,
            'variants' => [[
                'id' => $this->pan->id, 'sku' => 'PAN', 'name' => 'Standard', 'pricing_mode' => 'fixed', 'selling_unit' => 'piece',
                'price' => '2450', 'stock_quantity' => 999, 'is_active' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $this->pan->fresh()->stock_quantity);
        $this->assertSame([], $this->inventory->discrepancies());
    }
}
