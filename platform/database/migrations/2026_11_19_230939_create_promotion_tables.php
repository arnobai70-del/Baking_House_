<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coupons / automatic promotions and bundles (blueprint §06).
     */
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->string('discount_type', 16);
            $table->unsignedBigInteger('discount_value');
            $table->string('scope', 16)->default('order');
            $table->unsignedBigInteger('min_subtotal_paisa')->default(0);
            $table->unsignedBigInteger('max_discount_paisa')->nullable();
            $table->json('audiences');
            $table->boolean('stackable')->default(false);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_promotion', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100);
            $table->unsignedBigInteger('discount_paisa');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['promotion_id', 'reference']);
        });

        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('discount_type', 16);
            $table->unsignedBigInteger('discount_value');
            $table->json('audiences');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['bundle_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
    }
};
