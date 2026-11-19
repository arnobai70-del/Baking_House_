<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock ledger + reservations (blueprint §04). Quantities are base units: grams for weight
     * variants, units otherwise. product_variants.stock_quantity (on hand) and reserved_quantity are
     * only ever changed by single conditional UPDATE statements in App\Services\Inventory\InventoryService.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedBigInteger('reserved_quantity')->default(0)->after('stock_quantity');
            $table->timestamp('low_stock_alerted_at')->nullable()->after('low_stock_threshold');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->bigInteger('quantity');
            $table->unsignedBigInteger('balance_after');
            $table->nullableMorphs('reference');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('quantity');
            $table->string('reference', 100)->index();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        /* Opening balances so the ledger reconciles with existing stock. */
        $now = now();
        DB::table('product_variants')->where('stock_quantity', '>', 0)->orderBy('id')->each(function ($variant) use ($now): void {
            DB::table('inventory_movements')->insert([
                'product_variant_id' => $variant->id,
                'type' => 'opening',
                'quantity' => $variant->stock_quantity,
                'balance_after' => $variant->stock_quantity,
                'reason' => 'Opening balance when the stock ledger was introduced',
                'created_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('inventory_movements');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['reserved_quantity', 'low_stock_alerted_at']));
    }
};
