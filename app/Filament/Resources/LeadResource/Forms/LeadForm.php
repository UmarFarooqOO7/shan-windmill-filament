<?php

namespace App\Filament\Resources\LeadResource\Forms;

use App\Filament\Resources\InvoiceResource; // Keep if used elsewhere, or remove if not
use App\Models\Lead;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form; // Keep if used elsewhere, or remove if not
use Filament\Facades\Filament;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;

class LeadForm
{
    public static function make(): array
    {
        // Check if current user is admin
        $user = Filament::auth()->user();
        $isAdmin = $user?->is_admin ?? false;

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
                                            ->searchable()
                                            ->afterStateUpdated(function ($state, $record, $set) {
                                                if ($record && $state) {
                                                    // We'll let the observer handle the notification
                                                    // This is just for immediate feedback in the UI
                                                    $record->status_id = $state;
                                                }
                                            }),
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
                                        ->searchable()
                                        ->afterStateUpdated(function ($state, $record, $set) {
                                            if ($record && $state) {
                                                // We'll let the observer handle the notification
                                                // This is just for immediate feedback in the UI
                                                $record->setout_id = $state;
                                            }
                                        }),
                                    Forms\Components\TimePicker::make('time_on')
                                        ->visible($isAdmin),
                                    Forms\Components\TimePicker::make('time_en')
                                        ->visible($isAdmin),
                                    Forms\Components\TextInput::make('setout_st')
                                        ->maxLength(255)
                                        ->visible($isAdmin),
                                    Forms\Components\TextInput::make('setout_en')
                                        ->maxLength(255)
                                        ->visible($isAdmin),
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
                                        ->prefix('$')
                                        ->visible($isAdmin),
                                    Forms\Components\Placeholder::make('total_cleared')
                                        ->label('Amount Cleared')
                                        ->content(function ($record) {
                                            if (!$record)
                                                return '$0.00';
                                            return '$' . number_format($record->leadAmounts()->sum('amount_cleared'), 2);
                                        })
                                        ->visible($isAdmin),
                                    Forms\Components\Placeholder::make('total_remaining')
                                        ->label('Amount Remaining')
                                        ->content(function ($record) {
                                            if (!$record)
                                                return '$0.00';
                                            $remaining = $record->amount_owed - $record->leadAmounts()->sum('amount_cleared');
                                            return '$' . number_format($remaining, 2);
                                        })
                                        ->visible($isAdmin),
                                    Forms\Components\Select::make('writ_id')
                                        ->label('Writ Status')
                                        ->relationship('writStatus', 'name')
                                        ->preload()
                                        ->searchable()
                                        ->afterStateUpdated(function ($state, $record, $set) {
                                            if ($record && $state) {
                                                // We'll let the observer handle the notification
                                                // This is just for immediate feedback in the UI
                                                $record->writ_id = $state;
                                            }
                                        }),
                                    Forms\Components\TextInput::make('lbx')
                                        ->maxLength(255),
                                ]),
                            Forms\Components\Section::make('Create Invoice Actions')
                                ->schema([
                                    Actions::make([
                                        Action::make('createInvoiceFromLead')
                                            ->label('Create Invoice & Edit')
                                            ->icon('heroicon-o-document-plus')
                                            ->url(fn (Lead $record): string => route('invoices.createFromLead', ['lead' => $record->id])) // Pass lead ID
                                            ->openUrlInNewTab()
                                            ->requiresConfirmation(false)
                                            ->color('primary'),
                                    ])->fullWidth(),
                                ])->collapsible()->collapsed(false),

                            Forms\Components\Section::make('Payment History')
                                ->schema([
                                    TableRepeater::make('leadAmounts')
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
                                            Forms\Components\TextInput::make('description')
                                                ->label('Description')
                                                ->columnSpanFull(),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable(false)
                                        ->columnSpanFull()
                                        ->addActionLabel('Add Payment')
                                        ->label('Payment Details')
                                        ->grid(2),
                                ])
                                ->visible($isAdmin)
                        ]),

                    Forms\Components\Tabs\Tab::make('Additional Info')
                        ->schema([
                            Forms\Components\Grid::make()
                                ->schema([
                                    Forms\Components\TextInput::make('locs')
                                        ->maxLength(255)
                                        ->visible($isAdmin),
                                    Forms\Components\Textarea::make('notes')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ])
                ->contained()
                ->columnSpanFull()
        ];
    }

    // Removed duplicate form method
}
