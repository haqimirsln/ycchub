<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Set by the exco for multi-day trips; empty means "start to end time".
            $table->decimal('volunteer_hours', 5, 1)->nullable()->after('counts_for_activity');
        });

        Schema::create('program_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_feedback');
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('volunteer_hours');
        });
    }
};
