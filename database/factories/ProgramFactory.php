<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(5, 30))->setTime(8, 0);

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => 'beach_cleanup',
            'venue' => 'Pantai Teluk Ketapang',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'max_capacity' => 30,
            'status' => 'published',
            'counts_for_activity' => true,
        ];
    }

    public function daysFromNow(int $days): static
    {
        return $this->state(function () use ($days) {
            $start = now()->addDays($days)->setTime(8, 0);

            return ['starts_at' => $start, 'ends_at' => $start->copy()->addHours(4)];
        });
    }
}
