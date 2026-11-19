<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-editable settings (e.g. storefront languages) and Bangla columns on the SAME rows —
     * translations never duplicate product or category records.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('summary_bn')->nullable()->after('summary');
            $table->text('description_bn')->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('short_description_bn', 500)->nullable()->after('short_description');
            $table->text('description_bn')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['short_description_bn', 'description_bn']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn(['summary_bn', 'description_bn']));
        Schema::dropIfExists('settings');
    }
};
