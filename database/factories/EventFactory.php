<?php

namespace Database\Factories;

use App\Models\User; // Import User model
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon; // Import Carbon

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $allDay = $this->faker->boolean(20); // 20% chance of being an all-day event

        return [
            'title' => $this->faker->sentence(3),
            'start_at' => $start,
            'end_at' => $allDay ? null : Carbon::instance($start)->addHours($this->faker->numberBetween(1, 5)),
            'description' => $this->faker->paragraph(2),
            'all_day' => $allDay,
            'user_id' => User::factory() ?? User::first()?->id, // Assign to a new or existing user
            'is_lead_setout' => false, // Add this line
            'lead_id' => null, // Add this line
        ];
    }
}
