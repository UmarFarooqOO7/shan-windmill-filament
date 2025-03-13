<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Status;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'lead' => ['Executed', 'Cancelled', 'Pending', 'Miscellaneous'],
            'setout' => ['Scheduled', 'Rescheduled', 'Cancelled', 'Pending', 'Missed', 'Vacated', 'Skipped'],
            'writ' => ['Writ approved', 'Writ application', 'Writ scheduled', 'Writ executed'],
        ];

        if (Status::count() < 1) {
            foreach ($statuses as $type => $names) {
                foreach ($names as $name) {
                    Status::create(['name' => $name, 'type' => $type]);
                }
            }
        }
    }
}
