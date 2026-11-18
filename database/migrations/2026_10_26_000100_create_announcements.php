<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Club emails from the MT (promotions, news, reminders) and a way for members
 * to turn them off. Adds only; nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->string('subject', 150);
                $table->text('body');
                $table->string('button_label', 40)->nullable();
                $table->string('button_url', 500)->nullable();
                $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
                $table->text('audience')->nullable();
                $table->string('audience_label', 255)->nullable();
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->longText('pending_ids')->nullable();
                $table->string('last_error', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finished_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'email_opt_out')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('email_opt_out')->default(false)->after('member_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        if (Schema::hasColumn('users', 'email_opt_out')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email_opt_out'));
        }
    }
};
