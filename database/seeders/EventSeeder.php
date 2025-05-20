<?php

namespace Database\Seeders;

use App\Models\Event; // Import Event model
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Event::factory()->count(20)->create(); // Create 20 sample events
    }
}
