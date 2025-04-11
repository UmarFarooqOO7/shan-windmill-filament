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
            ->query(fn () =>
                Status::where('type', 'setout')
                    ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
                    ->leftJoin('leads', 'statuses.id', '=', 'leads.setout_id')
                    ->groupBy('statuses.id')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Status'),
                TextColumn::make('lead_count')
                    ->label('Total Leads')
                    ->sortable(),
            ])
            ->paginated(false)
            ->striped()
            ->defaultSort('lead_count', 'desc');
    }
}
