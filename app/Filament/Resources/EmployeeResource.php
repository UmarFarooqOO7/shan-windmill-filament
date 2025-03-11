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
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use App\Models\TimeEntry;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;

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
                Action::make('clockInOut')
                    ->label(fn (Employee $record) => $record->timeEntries()->whereNull('clock_out')->exists() ? 'Clock Out' : 'Clock In')
                    ->color(fn (Employee $record) => $record->timeEntries()->whereNull('clock_out')->exists() ? 'danger' : 'success')
                    ->action(function (Employee $record) {
                        if ($record->timeEntries()->whereNull('clock_out')->exists()) {
                            $timeEntry = $record->timeEntries()->whereNull('clock_out')->latest()->first();
                            if ($timeEntry) {
                                $timeEntry->update([
                                    'clock_out' => Carbon::now(),
                                ]);
                                Notification::make()
                                    ->title('Clocked out successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('No active clock in found')
                                    ->danger()
                                    ->send();
                            }
                        } else {
                            TimeEntry::create([
                                'employee_id' => $record->id,
                                'clock_in' => Carbon::now(),
                            ]);
                            Notification::make()
                                ->title('Clocked in successfully')
                                ->success()
                                ->send();
                        }
                    })
                    ->button()
                    ->extraAttributes(['class' => 'me-4']),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    ViewAction::make(),
                    Action::make('addTime')
                        ->label('Add Time')
                        ->icon('heroicon-o-clock')
                        ->form([
                            DateTimePicker::make('clock_in')
                                ->required(),
                            DateTimePicker::make('clock_out')
                                ->nullable(),
                        ])
                        ->action(function (Employee $record, array $data) {
                            TimeEntry::create([
                                'employee_id' => $record->id,
                                'clock_in' => $data['clock_in'],
                                'clock_out' => $data['clock_out'],
                            ]);
                            Notification::make()
                                ->title('Time added successfully')
                                ->success()
                                ->send();
                        }),
                ])->label('Actions')->tooltip('Click to see more actions'),
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
