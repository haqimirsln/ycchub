<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MT feedback (October 2026): superadmin / admin (MT) / member roles, teams,
 * alumni, free-text faculty, merit and demerit, event committee (AJK) and an
 * activity log. Existing members and their data are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Users ---------------------------------------------------------
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->change();
            $table->string('team', 30)->nullable()->after('faculty');
            $table->string('member_type', 10)->default('student')->after('team');
        });

        // Old "exco" accounts become MT admins.
        DB::table('users')->where('role', 'exco')->update(['role' => 'admin']);

        // The system owner becomes the superadmin; if that account isn't here,
        // the oldest admin does, so there is always one.
        $owner = env('SUPERADMIN_EMAIL', 'haqimi150@gmail.com');
        DB::table('users')->where('email', $owner)->update(['role' => 'superadmin', 'account_status' => 'approved']);
        if (! DB::table('users')->where('role', 'superadmin')->exists()) {
            $first = DB::table('users')->where('role', 'admin')->orderBy('id')->value('id');
            if ($first) {
                DB::table('users')->where('id', $first)->update(['role' => 'superadmin']);
            }
        }

        // Faculty is typed in now (names change), so store full names instead of codes.
        $names = [
            'FSSM' => 'Faculty of Science and Marine Environment',
            'FTKK' => 'Faculty of Ocean Engineering Technology',
            'FSKM' => 'Faculty of Computer Science and Mathematics',
            'FSPA' => 'Faculty of Science Fisheries and Aquaculture',
            'FSMA' => 'Faculty of Food Science and Agrotechnology',
            'FBESD' => 'Faculty of Business, Economics and Social Development',
            'FPM' => 'Faculty of Maritime Studies',
        ];
        foreach ($names as $code => $name) {
            DB::table('users')->where('faculty', $code)->update(['faculty' => $name]);
        }

        // ---- Events: committee ------------------------------------------------
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedSmallInteger('committee_count')->nullable()->after('max_capacity');
        });

        Schema::create('program_committee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position', 100);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'program_id']);
        });

        // ---- Merit and demerit ------------------------------------------------
        Schema::create('merit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('points'); // positive = merit, negative = demerit
            $table->string('source', 20);   // attendance, break_bonus, milestone, committee, manual
            $table->string('auto_key', 60)->nullable(); // identifies automatic entries so they can be recalculated
            $table->foreignId('program_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason', 255);
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'auto_key']);
            $table->index(['user_id', 'source']);
        });

        // ---- Activity log ---------------------------------------------------------
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description', 255);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('merit_entries');
        Schema::dropIfExists('program_committee');
        Schema::table('programs', fn (Blueprint $table) => $table->dropColumn('committee_count'));
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['team', 'member_type']));
    }
};
