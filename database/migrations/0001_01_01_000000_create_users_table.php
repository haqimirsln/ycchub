<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('matric_no', 20)->unique();
            $table->string('full_name', 150);
            $table->string('email', 150)->unique();
            $table->string('phone_number', 20);
            $table->string('faculty', 100)->nullable();
            $table->string('programme', 150)->nullable();
            $table->unsignedTinyInteger('year_of_study')->nullable();
            $table->string('password');

            // Access
            $table->enum('role', ['member', 'exco', 'admin'])->default('member');
            $table->enum('account_status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamp('pdpa_consent_at')->nullable();

            // Activity (cached, recalculated by App\Services\ActivityStatusService)
            $table->enum('activity_status', ['new', 'active', 'at_risk', 'inactive'])->default('new');
            $table->date('last_attended_on')->nullable();
            $table->unsignedSmallInteger('total_attended')->default(0);
            $table->timestamp('activity_checked_at')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'account_status', 'activity_status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
