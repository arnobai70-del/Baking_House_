<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom cakes inside the existing marketplace (no second ordering system): a cake is a normal catalogue
 * product with a cake profile; each customer design is one cart line → one order line; delivery uses the
 * existing zones (with cake-specific switches) or store pickup; payment uses the existing payments with a
 * configurable advance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Which catalogue products can be designed as cakes, with their per-product rules.
        Schema::create('cake_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('lead_time_hours')->nullable(); // null = global standard lead time
            $table->unsignedTinyInteger('advance_percent')->nullable();   // null = global rule
            $table->boolean('full_payment_required')->default(false);
            $table->unsignedInteger('min_grams')->default(500);
            $table->unsignedInteger('max_grams')->default(5000);
            $table->unsignedInteger('step_grams')->default(500);
            $table->unsignedTinyInteger('max_tiers')->default(3);
            $table->boolean('allow_eggless')->default(false);
            $table->boolean('allow_sugar_free')->default(false);
            $table->boolean('allow_both_diets')->default(false);
            $table->text('ingredients')->nullable();
            $table->text('allergens')->nullable();
            $table->timestamps();
        });

        // Every priced choice (flavour, shape, tiers, filling, cream, diet, message, print, extras).
        Schema::create('cake_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cake_product_id')->nullable()->constrained()->cascadeOnDelete(); // null = all cake products
            $table->string('group', 16)->index();
            $table->string('code', 40);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('price_type', 12); // per_kg | flat | per_tier
            $table->unsignedBigInteger('price_paisa')->default(0);
            $table->boolean('is_test')->default(true); // test prices block checkout outside local / testing
            $table->boolean('requires_quote')->default(false);
            $table->string('complexity', 12)->default('standard'); // simple | standard | complex
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['cake_product_id', 'group', 'code']);
        });

        Schema::create('cake_blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('kind', 16)->default('closed'); // closed | urgent_open
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('address');
            $table->string('phone', 20)->nullable();
            $table->string('hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cake_designs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_token_hash', 64)->nullable()->index(); // guest designs
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->json('spec');
            $table->string('fulfilment', 16)->default('delivery'); // delivery | pickup
            $table->date('scheduled_for')->nullable();
            $table->string('time_slot', 32)->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft | in_cart | ordered
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('price_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('cake_design_id')->nullable()->after('bundle_id')->constrained()->cascadeOnDelete();
            $table->unique(['cart_id', 'cake_design_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('cake_design_id')->nullable()->after('bundle_id')->constrained()->nullOnDelete();
            $table->date('scheduled_for')->nullable()->index();
            $table->string('time_slot', 32)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('advance_paisa')->default(0)->after('total_paisa'); // 0 = no advance rule
            $table->string('fulfilment', 16)->default('delivery')->after('delivery_zone_id');
            $table->foreignId('pickup_location_id')->nullable()->after('fulfilment')->constrained()->nullOnDelete();
        });

        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->boolean('cake_delivery_enabled')->default(false);
            $table->unsignedBigInteger('cake_charge_paisa')->nullable();
            $table->unsignedBigInteger('cake_free_over_paisa')->nullable();
            $table->unsignedBigInteger('cake_min_order_paisa')->nullable();
            $table->boolean('cake_tiered_allowed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn(['cake_delivery_enabled', 'cake_charge_paisa', 'cake_free_over_paisa', 'cake_min_order_paisa', 'cake_tiered_allowed']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_location_id');
            $table->dropColumn(['advance_paisa', 'fulfilment']);
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cake_design_id');
            $table->dropColumn(['scheduled_for', 'time_slot']);
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'cake_design_id']);
            $table->dropConstrainedForeignId('cake_design_id');
        });
        Schema::dropIfExists('cake_designs');
        Schema::dropIfExists('pickup_locations');
        Schema::dropIfExists('cake_blackout_dates');
        Schema::dropIfExists('cake_options');
        Schema::dropIfExists('cake_products');
    }
};
