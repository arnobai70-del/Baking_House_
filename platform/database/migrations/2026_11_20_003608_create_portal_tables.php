<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6 — business & reseller portals: quotations (RFQ → quote → accept → order), team
     * invitations, saved organization delivery addresses, and accepted quotations as cart lines.
     */
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            /* Null for a public request (sales lead); set for an approved business organization. */
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name', 120);
            $table->string('contact_phone', 11);
            $table->string('contact_email')->nullable();
            $table->string('status', 16)->default('requested')->index();
            $table->text('request_text');
            $table->text('customer_note')->nullable();
            /* Terms shown to the customer with the quote; the internal note is staff-only. */
            $table->text('terms')->nullable();
            $table->text('internal_note')->nullable();
            $table->unsignedBigInteger('total_paisa')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->foreignId('quoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            /* Null for a custom line (e.g. installation) that holds no stock. */
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('description');
            /* Base units (grams for weight variants), fixed by the quote: never editable by the buyer. */
            $table->unsignedInteger('quantity');
            $table->string('quantity_label', 40);
            $table->unsignedBigInteger('line_paisa');
            $table->timestamps();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('bundle_id')->constrained()->cascadeOnDelete();
            $table->unique(['cart_id', 'quotation_id']);
        });

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 16);
            /* sha256 of the one-time token; the token itself is never stored. */
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
        });

        Schema::create('organization_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('recipient_name', 120);
            $table->string('phone', 11);
            $table->string('line');
            $table->string('area', 120);
            $table->string('district', 40);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_addresses');
        Schema::dropIfExists('organization_invitations');
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique(['cart_id', 'quotation_id']);
            $table->dropConstrainedForeignId('quotation_id');
        });
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
