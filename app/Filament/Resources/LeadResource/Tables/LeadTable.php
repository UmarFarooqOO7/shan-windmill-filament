<?php

namespace App\Filament\Resources\LeadResource\Tables;

use App\Models\Lead;
use App\Models\Status;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\ActionsPosition;

class LeadTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('teams.name')
                    ->label('REF')
                    ->listWithLineBreaks()
                    ->searchable(),
                Tables\Columns\TextColumn::make('plaintiff')
                    ->label('Plaintiff')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('defendant_first_name')
                    ->label('Defendant')
                    ->formatStateUsing(fn($record) => $record->defendant_first_name . ' ' . $record->defendant_last_name)
                    ->searchable(['defendant_first_name', 'defendant_last_name'])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('county')
                    ->label('County')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('state')
                    ->label('State')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('zip')
                    ->label('Zip')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('case_number')
                    ->label('Case Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('setout_date')
                    ->label('Setout Date')
                    ->date()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('setout_time')
                    ->label('Setout Time')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('setoutStatus.name')
                    ->label('Setout Status')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('writStatus.name')
                    ->label('Writ Status')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('lbx')
                    ->label('LBX')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vis_setout')
                    ->label('Vis-LO')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vis_to')
                    ->label('Vis-TO')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('time_on')
                    ->label('Time Start')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('setout_st')
                    ->label('Setout Start')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('setout_en')
                    ->label('Setout End')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('time_en')
                    ->label('Time End')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('locs')
                    ->label('LOCS')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            // filters for the leads table
            ->filters([
                Tables\Filters\SelectFilter::make('teams')
                    ->relationship('teams', 'name')
                    ->multiple()
                    ->preload()
                    ->label('REF'),
                Tables\Filters\SelectFilter::make('status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('setout_id')
                    ->label('Setout Status')
                    ->relationship('setoutStatus', 'name')
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('writ_id')
                    ->label('Writ Status')
                    ->relationship('writStatus', 'name')
                    ->preload()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('county')
                    ->options(fn() => Lead::distinct()->pluck('county', 'county')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('city')
                    ->options(fn() => Lead::distinct()->pluck('city', 'city')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('state')
                    ->options(fn() => Lead::distinct()->pluck('state', 'state')->filter()->sort())
                    ->multiple()
                    ->preload(),
                Tables\Filters\Filter::make('has_notes')
                    ->label('Has Notes')
                    ->query(fn($query) => $query->whereNotNull('notes')->where('notes', '!=', ''))
                    ->toggle(),
            ])
            ->filtersFormColumns(3)

            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
            ], position: ActionsPosition::BeforeColumns)

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])

            ->defaultSort('created_at', 'desc')
            ->persistSortInSession()
            ->persistFiltersInSession();
    }
}
