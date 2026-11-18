<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            $table->string('category', 40)->default('other');
            $table->string('venue', 150);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->unsignedInteger('max_capacity')->nullable(); // null = unlimited
            $table->string('poster_path')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('published');
            $table->boolean('counts_for_activity')->default(true);
            $table->string('checkin_code', 32)->unique();
            $table->boolean('checkin_open')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('program_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['registered', 'cancelled', 'attended', 'absent'])->default('registered');
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('checkin_method', ['manual', 'qr'])->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_registrations');
        Schema::dropIfExists('programs');
    }
};
