<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Simple key/value store for things admins change from the UI (Instagram token, social links).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Date ranges (semester breaks, exam weeks) that do not count toward inactivity.
        Schema::create('activity_pauses', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        // Cached Instagram posts. "api" rows come from the Graph API sync,
        // "manual" rows are post URLs an admin pasted in (rendered with Instagram's embed script).
        Schema::create('instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['api', 'manual'])->default('api');
            $table->string('ig_id', 64)->nullable()->unique();
            $table->string('media_type', 20)->nullable();
            $table->text('caption')->nullable();
            $table->string('permalink', 255);
            $table->string('image_path')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'posted_at']);
        });

        // Who exported member contact data, and when (PDPA accountability).
        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('export_type', 50);
            $table->string('filters', 255)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('instagram_posts');
        Schema::dropIfExists('activity_pauses');
        Schema::dropIfExists('settings');
    }
};
