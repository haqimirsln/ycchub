<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MT positions per session (e.g. Setiausaha, 2026/2027), so each year's MT is kept
 * on record after handover. Seeds the 2026/2027 directory from the club's list:
 * people who already have an account are linked and made MT; the rest are kept by
 * name and matric, and get linked when they sign up and are approved.
 */
return new class extends Migration
{
    private const SESSION = '2026/2027';

    private const LIST = [
        ['presiden', 'S77008', 'Hazirah binti Mohd Azhar'],
        ['timbalan_presiden', 'S76276', 'Nursyaheera binti Mohd Hisham'],
        ['naib_presiden', 'D83514', 'Kishankumar a/l Kannan'],
        ['setiausaha', 'S79880', 'Mimie Farhana bt Mohd Hafiza'],
        ['timbalan_setiausaha', 'S75887', 'Nur Faiqah Dayana binti Muhammad Zakri Chan'],
        ['bendahari', 'S81761', 'Joyce Neo Yong Xin'],
        ['ketua_kepimpinan', 'D83422', "Nur'ain Sofea binti Muhammad Razi"],
        ['ketua_sukarelawan', 'S80081', 'Nurul Ashikin Ahamadi'],
        ['ketua_multimedia', 'S80740', 'Nur Adrieana Batriesyia binti Mohd Zamri'],
        ['ketua_keusahawanan', 'S75795', 'Che Riezham Amsyar bin Che Rizal Narami'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mt_terms')) {
            Schema::create('mt_terms', function (Blueprint $table) {
                $table->id();
                $table->string('session', 20);
                $table->string('position', 40);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('holder_name', 150)->nullable();
                $table->string('holder_matric', 20)->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['session', 'position']);
                $table->index('holder_matric');
            });
        }

        if (DB::table('mt_terms')->exists()) {
            return;
        }

        $now = now();
        foreach (self::LIST as $i => [$position, $matric, $name]) {
            $user = DB::table('users')->where('matric_no', $matric)->first();
            DB::table('mt_terms')->insert([
                'session' => self::SESSION,
                'position' => $position,
                'user_id' => $user?->id,
                'holder_name' => $user->full_name ?? $name,
                'holder_matric' => $matric,
                'sort' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($user && $user->account_status === 'approved' && $user->role === 'member') {
                DB::table('users')->where('id', $user->id)->update(['role' => 'admin']);
            }
        }

        foreach (['mt_session' => self::SESSION, 'mt_advisor' => 'Prof. Madya Dr. Mohd Uzair bin Rusli', 'mt_public' => '1'] as $key => $value) {
            if (! DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        Cache::forget('ycc.settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('mt_terms');
    }
};
