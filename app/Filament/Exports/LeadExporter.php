<?php

namespace App\Filament\Exports;

use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('teams.name')
                ->label('rid'),
            ExportColumn::make('plaintiff')
                ->label('plaintiff'),
            ExportColumn::make('defendant_first_name')
                ->label('defendant_first_name'),
            ExportColumn::make('defendant_last_name')
                ->label('defendant_last_name'),
            ExportColumn::make('address')
                ->label('address'),
            ExportColumn::make('county')
                ->label('county'),
            ExportColumn::make('city')
                ->label('city'),
            ExportColumn::make('state')
                ->label('state'),
            ExportColumn::make('zip')
                ->label('zip'),
            ExportColumn::make('case_number')
                ->label('case_number'),
            ExportColumn::make('setout_date')
                ->label('setout_date'),
            ExportColumn::make('setout_time')
                ->label('setout_time'),
            ExportColumn::make('status.name')
                ->label('status'),
            ExportColumn::make('writStatus.name')
                ->label('writ'),
            ExportColumn::make('setoutStatus.name')
                ->label('setout'),
            ExportColumn::make('lbx')
                ->label('lbx'),
            ExportColumn::make('vis_setout')
                ->label('vis_setout'),
            ExportColumn::make('vis_to')
                ->label('vis_to'),
            ExportColumn::make('notes')
                ->label('notes'),
            ExportColumn::make('time_on')
                ->label('time_on'),
            ExportColumn::make('setout_st')
                ->label('setout_st'),
            ExportColumn::make('setout_en')
                ->label('setout_en'),
            ExportColumn::make('time_en')
                ->label('time_en'),
            ExportColumn::make('locs')
                ->label('locs'),
            ExportColumn::make('amount_owed')
                ->label('amount_owed'),
            ExportColumn::make('amount_cleared')
                ->label('amount_cleared'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your lead export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
