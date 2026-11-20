<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 7 — real-image approval workflow (provenance + licence + derivatives) and the CRM
     * (leads / pipeline, activities, tasks, tags).
     */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            /* The upload lives on the PRIVATE disk; public derivatives exist only while approved. */
            $table->string('original_path')->nullable()->after('path');
            $table->string('alt_text_bn')->nullable()->after('alt_text');
            $table->boolean('is_primary')->default(false)->after('sort_order');
            $table->string('source_type', 24)->nullable();
            $table->string('creator', 120)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('license_type', 60)->nullable();
            $table->string('license_reference', 160)->nullable();
            $table->date('license_expires_at')->nullable();
            $table->boolean('authenticity_attested')->default(false);
            $table->string('mime', 32)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->json('renditions')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source', 24)->index();
            $table->string('stage', 16)->default('new')->index();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 11)->nullable();
            $table->string('contact_email')->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete();
            /* Null = shared queue: every CRM user sees it until someone is responsible. */
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('estimated_value_paisa')->nullable();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'stage']);
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('type', 16);
            $table->text('body');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('title');
            $table->timestamp('due_at')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->timestamps();
        });

        Schema::create('crm_taggables', function (Blueprint $table) {
            $table->foreignId('crm_tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->primary(['crm_tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_taggables');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['original_path', 'alt_text_bn', 'is_primary', 'source_type', 'creator', 'source_url', 'license_type', 'license_reference',
                'license_expires_at', 'authenticity_attested', 'mime', 'width', 'height', 'bytes', 'sha256', 'renditions', 'reviewed_at', 'review_note']);
        });
    }
};
