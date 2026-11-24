<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom cakes, Part 2: the admin design library (templates, toppers, decorations, borders, patterns,
 * sticker packs, fonts, palettes), private customer photo uploads with print approval, and saved / shared
 * designs with their 3D snapshot and top-design image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cake_library_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                // template | topper | decoration | border | pattern | sticker_pack | font | palette
            $table->string('code', 40);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('price_paisa')->default(0);
            $table->boolean('is_test')->default(true);  // TEST until the owner approves the price
            $table->string('complexity', 12)->default('standard');
            $table->json('allowed_shapes')->nullable(); // null = every shape
            $table->json('payload');                    // kind-specific drawing data (own vector shapes only)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['kind', 'code']);
        });

        Schema::create('cake_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_token_hash', 64)->nullable()->index();
            $table->foreignId('cake_design_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');                      // private disk, never public
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('bytes');
            $table->string('sha256', 64);
            $table->timestamp('rights_confirmed_at');
            $table->string('review_status', 12)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('cake_designs', function (Blueprint $table) {
            $table->string('name', 80)->nullable()->after('uuid');
            $table->string('share_token', 40)->nullable()->unique()->after('name');
            $table->string('snapshot_path')->nullable()->after('price_snapshot');
            $table->string('top_path')->nullable()->after('snapshot_path');
            $table->foreignId('copied_from_id')->nullable()->after('top_path')->constrained('cake_designs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cake_designs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_id');
            $table->dropColumn(['name', 'share_token', 'snapshot_path', 'top_path']);
        });
        Schema::dropIfExists('cake_uploads');
        Schema::dropIfExists('cake_library_items');
    }
};
