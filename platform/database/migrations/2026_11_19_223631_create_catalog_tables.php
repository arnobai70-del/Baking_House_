<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog (blueprint §04, §09). Money in integer paisa, weight in integer grams.
     * Stock here is a simple on-hand figure in base units; the Phase 4 stock ledger replaces it.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 32)->default('box');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->text('search_keywords')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('price_from_paisa')->nullable()->index();
            $table->boolean('in_stock')->default(false)->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name')->default('Standard');
            $table->string('pricing_mode', 16);
            $table->string('selling_unit', 16);
            $table->unsignedInteger('pack_size')->nullable();
            $table->unsignedBigInteger('price_paisa')->nullable();
            $table->unsignedBigInteger('compare_at_paisa')->nullable();
            $table->unsignedBigInteger('per_kg_paisa')->nullable();
            $table->unsignedInteger('min_grams')->nullable();
            $table->unsignedInteger('max_grams')->nullable();
            $table->unsignedInteger('step_grams')->nullable();
            $table->json('preset_grams')->nullable();
            $table->unsignedBigInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('alt_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('review_status', 16)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
