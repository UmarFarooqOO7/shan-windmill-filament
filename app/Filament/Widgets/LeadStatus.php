<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Status;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class LeadStatus extends BaseWidget
{
    protected static ?string $heading = 'Lead Status Counts';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () =>
                Status::where('type', 'lead')
                    ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
                    ->leftJoin('leads', 'statuses.id', '=', 'leads.status_id')
                    ->groupBy('statuses.id')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Status')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lead_count')
                    ->label('Total Leads')
                    ->sortable(),
            ])
            ->defaultSort('lead_count', 'desc');
    }
}
