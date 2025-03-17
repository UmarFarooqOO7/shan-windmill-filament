<?php

namespace App\Filament\Resources\LeadResource\Forms;

use App\Models\Lead;
use App\Models\Status;
use Filament\Forms;
use Filament\Forms\Form;

class LeadForm
{
    public static function make(): array
    {
        return [
            Forms\Components\Tabs::make('Lead Details')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Basic & Client Info')
                        ->schema([
                            Forms\Components\Split::make([
                                Forms\Components\Section::make('Basic Info')
                                    ->schema([
                                        Forms\Components\Select::make('rid')
                                            ->label('RID')
                                            ->searchable()
                                            ->relationship('teams', 'name')
                                            ->preload()
                                            ->multiple(),
                                        Forms\Components\Select::make('status_id')
                                            ->label('Status')
                                            ->relationship('status', 'name')
                                            ->preload()
                                            ->searchable(),
                                        Forms\Components\TextInput::make('case_number'),
                                    ]),
                                Forms\Components\Section::make('Client Info')
                                    ->schema([
                                        Forms\Components\TextInput::make('plaintiff')
                                            ->maxLength(255)
                                            ->required(),
                                        Forms\Components\TextInput::make('defendant_first_name')
                                            ->label('Defendant First Name')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('defendant_last_name')
                                            ->label('Defendant Last Name')
                                            ->maxLength(255),
                                    ]),
                            ])->from('md'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Address Details')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->columns(2)
                                ->schema([
                                    Forms\Components\TextInput::make('address')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('county')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('city')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('state')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('zip')
                                        ->maxLength(255),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make('Setout Info')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->columns(2)
                                ->schema([
                                    Forms\Components\DatePicker::make('setout_date'),
                                    Forms\Components\TimePicker::make('setout_time'),
                                    Forms\Components\Select::make('setout_id')
                                        ->label('Setout Status')
                                        ->relationship('setoutStatus', 'name')
                                        ->preload()
                                        ->searchable(),
                                    Forms\Components\TimePicker::make('time_on'),
                                    Forms\Components\TimePicker::make('time_en'),
                                    Forms\Components\TextInput::make('setout_st')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('setout_en')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('vis_setout')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('vis_to')
                                        ->maxLength(255),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make('Financial Details')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->columns(3)
                                ->schema([
                                    Forms\Components\TextInput::make('amount_owed')
                                        ->numeric()
                                        ->prefix('$'),
                                    Forms\Components\Placeholder::make('total_cleared')
                                        ->label('Amount Cleared')
                                        ->content(function ($record) {
                                            if (!$record) return '$0.00';
                                            return '$' . number_format($record->leadAmounts()->sum('amount_cleared'), 2);
                                        }),
                                    Forms\Components\Placeholder::make('total_remaining')
                                        ->label('Amount Remaining')
                                        ->content(function ($record) {
                                            if (!$record) return '$0.00';
                                            $remaining = $record->amount_owed - $record->leadAmounts()->sum('amount_cleared');
                                            return '$' . number_format($remaining, 2);
                                        }),
                                    Forms\Components\Select::make('writ_id')
                                        ->label('Writ Status')
                                        ->relationship('writStatus', 'name')
                                        ->preload()
                                        ->searchable(),
                                    Forms\Components\TextInput::make('lbx')
                                        ->maxLength(255),
                                ]),

                            Forms\Components\Section::make('Payment History')
                                ->schema([
                                    Forms\Components\Repeater::make('leadAmounts')
                                        ->relationship()
                                        ->schema([
                                            Forms\Components\TextInput::make('amount_cleared')
                                                ->numeric()
                                                ->prefix('$')
                                                ->required(),
                                            Forms\Components\DatePicker::make('payment_date')
                                                ->label('Payment Date')
                                                ->required()
                                                ->default(now()),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable(false)
                                        ->columnSpanFull()
                                        ->addActionLabel('Add Payment')
                                        ->label('Payments'),
                                ])->columnSpanFull(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Additional Info')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\TextInput::make('locs')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('notes')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->contained() // This removes the scrollbar
                ->columnSpanFull()
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(self::make());
    }
}
