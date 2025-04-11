<?php

namespace App\Filament\Widgets;

use App\Models\Status;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class SetoutStatus extends BaseWidget
{
    protected static ?string $heading = 'Setout Status';

    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = '4';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn() =>
                Status::where('type', 'setout')
                    ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
                    ->leftJoin('leads', 'statuses.id', '=', 'leads.setout_id')
                    ->groupBy('statuses.id')
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
                ['tableFilters[setout_id][value]' => $record->id]
            ))
            ->paginated(false)
            ->striped()
            ->poll('30s')
            ->defaultSort('lead_count', 'desc');
    }
}
