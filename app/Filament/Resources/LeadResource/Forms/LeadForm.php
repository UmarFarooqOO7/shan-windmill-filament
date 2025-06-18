<?php

namespace App\Filament\Resources\LeadResource\Forms;

use App\Filament\Resources\InvoiceResource;
use App\Models\Lead; // Ensure Lead model is imported
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Facades\Filament;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Filament\Forms\Components\Repeater; // Add this for the invoices repeater
use Filament\Forms\Components\Grid; // To control layout within repeater
use Filament\Support\Enums\Alignment; // For aligning action button
use Filament\Forms\Get;
use Filament\Forms\Set;

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
                                        ->visible($isAdmin)
                                        ->live(onBlur: true) // Update on blur to avoid too many updates while typing
                                        ->afterStateUpdated(function (Get $get, Set $set, $state, ?Lead $record) {
                                            $amountOwedNumeric = (float) $state;
                                            $totalClearedNumeric = 0;

                                            if ($record) {
                                                // Fallback to record if not in form state (e.g., initial load before repeater interaction)
                                                $totalClearedNumeric = $record->leadAmounts()->sum('amount_cleared');
                                            }

                                            $newRemainingAmount = $amountOwedNumeric - $totalClearedNumeric;
                                            $set('total_remaining', number_format($newRemainingAmount, 2));
                                            // Optionally, update total_cleared if it needs to reflect repeater changes
                                            // when amount_owed is the trigger for an update cycle.
                                            // $set('total_cleared', number_format($totalClearedNumeric, 2));
                                        }),
                                    Forms\Components\TextInput::make('total_cleared')
                                        ->label('Amount Cleared')
                                        ->prefix('$')
                                        ->numeric()
                                        ->disabled()
                                        ->nullable()
                                        ->formatStateUsing(function (?Lead $record, Get $get): string {
                                            if (!$record && !$get('leadAmounts')) return '0.00';

                                            $totalClearedNumeric = 0;
                                            $leadAmountsItems = $get('leadAmounts');

                                            if (is_array($leadAmountsItems)) {
                                                foreach ($leadAmountsItems as $item) {
                                                    $totalClearedNumeric += (float)($item['amount_cleared'] ?? 0);
                                                }
                                            } elseif($record) {
                                                 $totalClearedNumeric = $record->leadAmounts()->sum('amount_cleared');
                                            }
                                            return number_format($totalClearedNumeric, 2);
                                        })
                                        ->visible($isAdmin),
                                    Forms\Components\TextInput::make('total_remaining')
                                        ->label('Amount Remaining')
                                        ->prefix('$')
                                        ->numeric() // Keep numeric for consistency, though disabled
                                        ->disabled()
                                        ->nullable()
                                        ->formatStateUsing(function (?Lead $record, Get $get): string {
                                            if (!$record && !$get('amount_owed')) return '0.00';

                                            $amountOwedNumeric = (float) $get('amount_owed');
                                            if (!$amountOwedNumeric && $record) {
                                                $amountOwedNumeric = (float) $record->amount_owed;
                                            }

                                            $totalClearedNumeric = 0;
                                            if($record) {
                                                 $totalClearedNumeric = $record->leadAmounts()->sum('amount_cleared');
                                            }

                                            $remaining = $amountOwedNumeric - $totalClearedNumeric;
                                            return number_format($remaining, 2);
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
                            Forms\Components\Section::make('Invoices')
                                ->description('Manage invoices related to this lead. You can create new invoices or view existing ones.')
                                ->headerActions([
                                    Action::make('createInvoiceFromLead')
                                        ->label('Create New Invoice')
                                        ->icon('heroicon-o-document-plus')
                                        ->url(fn(?Lead $record): string => $record ? route('invoices.createFromLead', ['lead' => $record->id]) : '#')
                                        ->openUrlInNewTab()
                                        ->disabled(fn(?Lead $record) => !$record)
                                        ->color('primary'),
                                ])
                                ->schema([
                                    Repeater::make('invoices')
                                        ->relationship('invoices') // Assumes 'invoices' relationship exists on Lead model
                                        ->schema([
                                            Forms\Components\TextInput::make('invoice_number')
                                                ->label('Invoice #')
                                                ->disabled()
                                                ->columnSpan(1),
                                            Forms\Components\DatePicker::make('invoice_date')
                                                ->label('Date')
                                                ->disabled()
                                                ->columnSpan(1),
                                            Forms\Components\TextInput::make('status')
                                                ->label('Status')
                                                ->disabled()
                                                ->columnSpan(1),
                                            Forms\Components\TextInput::make('total_amount')
                                                ->label('Total')
                                                ->numeric()
                                                ->prefix('$')
                                                ->disabled()
                                                ->columnSpan(1),
                                            Actions::make([
                                                Action::make('editInvoice')
                                                    ->label('Edit')
                                                    ->icon('heroicon-o-pencil-square')
                                                    ->url(fn(Forms\Get $get): string => InvoiceResource::getUrl('edit', ['record' => $get('id')]))
                                                    ->openUrlInNewTab(),
                                                Action::make('downloadInvoice')
                                                    ->label('Download PDF')
                                                    ->icon('heroicon-o-arrow-down-tray')
                                                    ->url(fn(Forms\Get $get): string => route('invoices.download', ['invoice' => $get('id')]))
                                                    ->openUrlInNewTab(),
                                            ])->columnSpan(2)->alignment(Alignment::End),
                                        ])
                                        ->columns(6) // Adjust total columns for the repeater items
                                        ->addable(false)
                                        ->deletable(false)
                                        ->reorderable(false)
                                        ->collapsible(false)
                                        ->collapsed(false)
                                        ->label('Existing Invoices')
                                        ->columnSpanFull()
                                        ->visible(fn(?Lead $record) => $record && $record->invoices()->exists()),

                                ])
                                ->collapsible()
                                ->collapsed(true),

                            Forms\Components\Section::make('Payment History')
                                ->description('Record payments made towards the lead. Payments can be added, edited, or deleted.')
                                ->schema([
                                    TableRepeater::make('leadAmounts')
                                        ->relationship()
                                        ->schema([
                                            Forms\Components\TextInput::make('amount_cleared')
                                                ->numeric()
                                                ->prefix('$')
                                                ->required()
                                                ->columnSpan(1), // Small width
                                            Forms\Components\DatePicker::make('payment_date')
                                                ->label('Payment Date')
                                                ->required()
                                                ->default(now())
                                                ->columnSpan(1), // Small width
                                            Forms\Components\TextInput::make('description')
                                                ->label('Description')
                                                ->columnSpan(2), // Widest possible for a 2+2 grid, or use columnSpanFull() if it's the only item in its row
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable(false)
                                        ->columnSpanFull()
                                        ->addActionLabel('Add Payment')
                                        ->label('Payment Details')
                                        ->grid(['default' => 1, 'md' => 2, 'lg' => 4]) // Adjust grid for responsiveness
                                        ->columns(4), // Set total columns for items inside
                                ])
                                ->visible($isAdmin)
                                ->collapsible()
                                ->collapsed(true),
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
                ->contained(true)
                ->columnSpanFull()
                ->persistTabInQueryString()
        ];
    }

    // Removed duplicate form method
}
