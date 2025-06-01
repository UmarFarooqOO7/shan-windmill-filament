<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('lead_id')
                    ->relationship('lead', 'plaintiff')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('invoice_number')
                    ->required()
                    ->maxLength(255)
                    ->unique(Invoice::class, 'invoice_number', ignoreRecord: true)
                    ->default(fn() => 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(5))),
                DatePicker::make('invoice_date')
                    ->required()
                    ->default(now()),
                DatePicker::make('due_date')
                    ->required(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('draft'),
                Select::make('template_id')
                    ->options([
                        'modern' => 'Modern',
                        'classic' => 'Classic',
                        'compact' => 'Compact',
                        'default' => 'Default',
                    ])
                    ->required()
                    ->default('default')
                    ->label('Invoice Template'),
                Textarea::make('notes')
                    ->columnSpanFull(),

                Section::make('Items')
                    ->headerActions([
                    ])
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(3),
                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $quantity = $state ?? 0;
                                        $set('total_price', $quantity * $unitPrice);
                                    })
                                    ->columnSpan(1),
                                TextInput::make('unit_price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                        $quantity = $get('quantity') ?? 0;
                                        $unitPrice = $state ?? 0;
                                        $set('total_price', $quantity * $unitPrice);
                                    })
                                    ->columnSpan(1),
                                TextInput::make('total_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->grid(1)
                            ->defaultItems(1)
                            ->addActionLabel('Add Invoice Item')
                            ->columnSpanFull()
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['total_price'] = ($data['quantity'] ?? 0) * ($data['unit_price'] ?? 0);
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['total_price'] = ($data['quantity'] ?? 0) * ($data['unit_price'] ?? 0);
                                return $data;
                            })
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->deleteAction(
                                fn(Forms\Components\Actions\Action $action) => $action->after(function (Forms\Get $get, Forms\Set $set) {
                                    self::updateTotals($get, $set);
                                })
                            ),
                    ])->columnSpanFull(),

                TextInput::make('subtotal')
                    ->numeric()
                    ->prefix('$')
                    ->readOnly()
                    ->default(0),
                TextInput::make('tax_rate')
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                        self::updateTotals($get, $set);
                    }),
                TextInput::make('tax_amount')
                    ->numeric()
                    ->prefix('$')
                    ->readOnly()
                    ->default(0),
                TextInput::make('total_amount')
                    ->numeric()
                    ->prefix('$')
                    ->readOnly()
                    ->default(0),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lead.plaintiff')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Created By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'draft' => 'gray',
                        'sent' => 'info',
                        'paid' => 'success',
                        'overdue' => 'warning',
                        'cancelled' => 'danger',
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function updateTotals(Forms\Get $get, Forms\Set $set): void
    {
        $items = $get('items') ?? [];
        $subtotal = 0;
        foreach ($items as $item) {
            $itemTotalPrice = $item['total_price'] ?? (($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0));
            $subtotal += $itemTotalPrice;
        }
        $set('subtotal', round($subtotal, 2));

        $taxRate = $get('tax_rate') ?? 0;
        $taxAmount = ($subtotal * $taxRate) / 100;
        $set('tax_amount', round($taxAmount, 2));

        $totalAmount = $subtotal + $taxAmount;
        $set('total_amount', round($totalAmount, 2));
    }
}
