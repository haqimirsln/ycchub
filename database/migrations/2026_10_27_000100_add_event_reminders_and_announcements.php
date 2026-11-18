<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Remembers who already got an event reminder, and whether an event was announced by email. Adds only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('program_registrations', 'reminded_at')) {
            Schema::table('program_registrations', function (Blueprint $table) {
                $table->timestamp('reminded_at')->nullable()->after('checkin_method');
            });
        }
        if (! Schema::hasColumn('programs', 'announced_at')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->timestamp('announced_at')->nullable();
                $table->foreignId('announcement_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('program_registrations', 'reminded_at')) {
            Schema::table('program_registrations', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        }
        if (Schema::hasColumn('programs', 'announced_at')) {
            Schema::table('programs', fn (Blueprint $table) => $table->dropColumn(['announced_at', 'announcement_id']));
        }
    }
};
