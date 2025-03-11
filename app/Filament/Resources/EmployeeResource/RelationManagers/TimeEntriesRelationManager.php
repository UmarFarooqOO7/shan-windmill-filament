<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\TimeEntry;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TimeEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'timeEntries';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('clock_in')
                    ->required(),
                DateTimePicker::make('clock_out')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clock_in')->sortable(),
                TextColumn::make('clock_out')->sortable(),
                TextColumn::make('total_time')->label('Total Time')
                    ->getStateUsing(function (TimeEntry $record) {
                        $clockIn = Carbon::parse($record->clock_in);
                        if ($record->clock_out) {
                            $clockOut = Carbon::parse($record->clock_out);
                            return $clockOut->longAbsoluteDiffForHumans($clockIn);
                        }
                        return 'N/A';
                    }),
            ])
            ->defaultSort('clock_in', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
