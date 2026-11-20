<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-configurable delivery zones (districts → charge). Charges are placeholders until a
     * courier agreement is in place.
     */
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->json('districts');
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('charge_paisa');
            $table->unsignedBigInteger('free_over_paisa')->nullable();
            $table->string('estimate')->nullable();
            $table->boolean('cod_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
