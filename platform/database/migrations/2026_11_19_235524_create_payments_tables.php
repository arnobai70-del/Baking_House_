<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payments, verified provider events (idempotency) and refunds. No card data is ever stored.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 24)->index();
            $table->string('mode', 8)->default('test');
            $table->string('status', 16)->index();
            $table->unsignedBigInteger('amount_paisa');
            $table->unsignedBigInteger('refunded_paisa')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->string('reference', 64)->unique();
            $table->string('provider_transaction_id', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_transaction_id']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24);
            $table->string('event_id', 100);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->boolean('signature_valid');
            $table->string('result', 32);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paisa');
            $table->string('status', 16);
            $table->text('reason');
            $table->string('provider_reference', 100)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
