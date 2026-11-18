<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Local/demo seeding: php artisan migrate:fresh --seed
     * Admin login: ADMIN01 (or admin@ycc.test) / password
     */
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['matric_no' => 'ADMIN01']);
        $admin->fill([
            'full_name' => 'YCC Admin',
            'email' => 'admin@ycc.test',
            'phone_number' => '0130000000',
            'pdpa_consent_at' => now(),
        ]);
        $admin->password = Hash::make('password');
        $admin->forceFill([
            'role' => 'superadmin',
            'account_status' => 'approved',
            'reviewed_at' => now()->subYear(),
            'created_at' => now()->subYear(),
        ])->save();

        $this->call(DemoSeeder::class);
    }
}
