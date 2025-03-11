<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager;
use App\Models\Employee;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                Fieldset::make('Times')
                    ->schema([
                        TextInput::make('comp_time')
                            ->numeric()
                            ->required(),
                        TextInput::make('vacation_time')
                            ->numeric()
                            ->required(),
                        TextInput::make('sick_time')
                            ->numeric()
                            ->required(),
                    ]),
                Fieldset::make('Accrual Rates')
                    ->schema([
                        TextInput::make('comp_time_accrual_rate')
                            ->numeric()
                            ->required(),
                        TextInput::make('vacation_time_accrual_rate')
                            ->numeric()
                            ->required(),
                        TextInput::make('sick_time_accrual_rate')
                            ->numeric()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->wrap(),
                TextColumn::make('email')->label('Email')->wrap(),
                TextColumn::make('comp_time')->label('Comp Time')->wrap(),
                TextColumn::make('vacation_time')->label('Vacation Time')->wrap(),
                TextColumn::make('sick_time')->label('Sick Time')->wrap(),
                TextColumn::make('comp_time_accrual_rate')->label('Comp Time Rate')->wrap(),
                TextColumn::make('vacation_time_accrual_rate')->label('Vacation Rate')->wrap(),
                TextColumn::make('sick_time_accrual_rate')->label('Sick Rate')->wrap(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ViewAction::make(),
                ]),
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
            TimeEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
