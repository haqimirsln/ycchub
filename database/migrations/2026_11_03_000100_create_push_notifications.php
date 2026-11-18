<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phone notifications: each phone a member turned them on for, a small send queue, and when an event was pushed. Adds only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash', 64)->unique();
                $table->string('public_key', 120);
                $table->string('auth_token', 60);
                $table->string('device', 120)->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_messages')) {
            Schema::create('push_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title', 120);
                $table->string('body', 240);
                $table->string('url', 500)->nullable();
                $table->string('tag', 60)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('programs', 'pushed_at')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->timestamp('pushed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_subscriptions');
        if (Schema::hasColumn('programs', 'pushed_at')) {
            Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('pushed_at'));
        }
    }
};
