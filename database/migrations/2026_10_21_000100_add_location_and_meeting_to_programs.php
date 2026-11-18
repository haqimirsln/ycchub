<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // in_person, online or hybrid
            $table->string('location_type', 20)->default('in_person')->after('venue');
            $table->decimal('latitude', 10, 7)->nullable()->after('location_type');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('meeting_url', 500)->nullable()->after('longitude');
            $table->string('meeting_info', 255)->nullable()->after('meeting_url');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'latitude', 'longitude', 'meeting_url', 'meeting_info']);
        });
    }
};
