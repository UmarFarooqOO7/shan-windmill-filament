<?php

namespace App\Filament\Widgets;

use App\Models\Status;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class LeadStatus extends BaseWidget
{
    protected static ?string $heading = 'Lead Status';

    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() =>
                Status::where('type', 'lead')
                    // ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
                    ->select([
                        'statuses.id',
                        'statuses.name',
                        DB::raw('COUNT(leads.id) as lead_count'),
                    ])
                    ->leftJoin('leads', 'statuses.id', '=', 'leads.status_id')
                    ->where('type', 'lead')
                    ->groupBy('statuses.id', 'statuses.name')
                    
                    // ->groupBy('statuses.id')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Status')
                    ->tooltip(fn(Status $record): string => "View all leads with {$record->name} status"),
                TextColumn::make('lead_count')
                    ->label('Total Leads')
                    ->sortable()
                    ->badge()
                    ->color(fn($record): string => $record->lead_count > 0 ? 'success' : 'gray')
                    ->alignCenter()
            ])
            ->recordUrl(fn(Status $record): string => route(
                'filament.admin.resources.leads.index',
                ['tableFilters[status_id][value]' => $record->id]
            ))
            ->paginated(false)
            ->striped()
            ->poll('30s')
            ->defaultSort('lead_count', 'desc');
    }
}
