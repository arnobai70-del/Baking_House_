<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders with an immutable pricing snapshot. Fulfillment status (status) and payment status
     * (payment_status) are separate. checkout_token makes order placement idempotent.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('checkout_token', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audience', 16);
            $table->string('status', 24)->index();
            $table->string('payment_status', 24)->index();
            $table->string('payment_method', 24);
            $table->string('customer_name');
            $table->string('customer_phone', 11)->index();
            $table->string('customer_email')->nullable();
            $table->json('shipping_address');
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('subtotal_paisa');
            $table->unsignedBigInteger('discount_paisa')->default(0);
            $table->unsignedBigInteger('delivery_paisa')->default(0);
            $table->unsignedBigInteger('total_paisa');
            $table->unsignedBigInteger('refunded_paisa')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->json('promotion_snapshot')->nullable();
            $table->json('pricing_snapshot');
            $table->string('guest_access_hash', 64)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->string('quantity_label', 40);
            $table->unsignedBigInteger('unit_paisa')->nullable();
            $table->unsignedBigInteger('per_kg_paisa')->nullable();
            $table->string('rate_source', 24)->nullable();
            $table->unsignedBigInteger('line_paisa');
            $table->unsignedBigInteger('standard_line_paisa');
            $table->json('components')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
