<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'matric_no' => 'S'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => '01'.fake()->numerify('########'),
            'faculty' => 'Faculty of Science and Marine Environment',
            'team' => 'volunteerism',
            'member_type' => 'student',
            'programme' => 'Marine Biology',
            'year_of_study' => fake()->numberBetween(1, 4),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'member',
            'account_status' => 'approved',
            'reviewed_at' => now(),
            'pdpa_consent_at' => now(),
            'activity_status' => 'new',
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['account_status' => 'pending', 'reviewed_at' => null]);
    }

    /** MT member (admin). Older tests call it exco(). */
    public function exco(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function superadmin(): static
    {
        return $this->state(fn () => ['role' => 'superadmin']);
    }

    public function joinedDaysAgo(int $days): static
    {
        return $this->state(fn () => ['created_at' => now()->subDays($days), 'reviewed_at' => now()->subDays($days)]);
    }
}
