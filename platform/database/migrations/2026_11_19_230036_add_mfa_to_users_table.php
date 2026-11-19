<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff TOTP MFA. The secret is stored encrypted (Eloquent "encrypted" cast with APP_KEY);
     * recovery codes are stored only as keyed hashes and are single-use.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedBigInteger('mfa_last_timestep')->nullable();
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mfa_secret', 'mfa_confirmed_at', 'mfa_last_timestep']);
        });
    }
};
