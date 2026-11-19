<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price lists (blueprint §05–06). Precedence: contract (organization list) → business/reseller
     * list → campaign list → retail volume list → variant standard price. Money in paisa.
     */
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('audience', 16)->index();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['audience', 'is_active']);
        });

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedBigInteger('price_paisa')->nullable();
            $table->unsignedBigInteger('per_kg_paisa')->nullable();
            $table->timestamps();
            $table->unique(['price_list_id', 'product_variant_id', 'min_quantity'], 'price_list_item_tier_unique');
        });

        Schema::create('scheduled_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('field', 32);
            $table->unsignedBigInteger('new_value_paisa')->nullable();
            $table->timestamp('effective_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('min_order_quantity')->default(1)->after('pack_size');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('min_order_quantity'));
        Schema::dropIfExists('scheduled_price_changes');
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_lists');
    }
};
