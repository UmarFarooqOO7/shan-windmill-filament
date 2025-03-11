<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeEntryResource\Pages;
use App\Filament\Resources\TimeEntryResource\RelationManagers;
use App\Models\TimeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->relationship('employee', 'name')
                    ->required(),
                DateTimePicker::make('clock_in')
                    ->required(),
                DateTimePicker::make('clock_out')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')->label('Employee')->sortable(),
                TextColumn::make('clock_in')->label('Clock In')->sortable(),
                TextColumn::make('clock_out')->label('Clock Out')->sortable(),
                TextColumn::make('total_time')->label('Calculated Time')->getStateUsing(function (TimeEntry $record) {
                    $clockIn = Carbon::parse($record->clock_in);
                    if ($record->clock_out) {
                        $clockOut = Carbon::parse($record->clock_out);
                        return $clockOut->longAbsoluteDiffForHumans($clockIn);
                    }
                    return 'N/A';
                }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('employee')
                    ->relationship('employee', 'name')
                    ->columnSpan(1),
                TernaryFilter::make('clock_out')
                    ->placeholder('All Time Entries')
                    ->trueLabel('Completed (Clocked out)')
                    ->falseLabel('Active (Still clocked in)')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('clock_out'),
                        false: fn (Builder $query) => $query->whereNull('clock_out'),
                    )
                    ->columnSpan(1),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeEntries::route('/'),
            'create' => Pages\CreateTimeEntry::route('/create'),
            'edit' => Pages\EditTimeEntry::route('/{record}/edit'),
        ];
    }
}
