<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Status;
use App\Traits\HasTeamScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LeadStatusChart extends ChartWidget
{
    use HasTeamScope;

    protected static ?string $heading = 'Leads by Status';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Get base query for user's leads
        $leadQuery = Lead::query();
        $leadQuery = $this->applyTeamScope($leadQuery);

        $data = $leadQuery->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->status => $item->count])
            ->toArray();

        // Get all possible lead statuses
        $statuses = Status::where('type', 'lead')
            ->pluck('name')
            ->toArray();

        // Ensure all statuses are represented in the data
        foreach ($statuses as $status) {
            if (!isset($data[$status])) {
                $data[$status] = 0;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Number of Leads',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#36A2EB',  // Blue
                        '#FF6384',  // Red
                        '#4BC0C0',  // Teal
                        '#FF9F40',  // Orange
                        '#9966FF',  // Purple
                        '#FFD700',  // Gold
                        '#32CD32',  // Lime Green
                        '#FF69B4',  // Hot Pink
                        '#4169E1',  // Royal Blue
                        '#FF4500',  // Orange Red
                    ],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
