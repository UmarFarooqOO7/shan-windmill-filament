<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\InvoiceResource; // Import InvoiceResource

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, RelationManager $livewire) {
                        $this->updateItemTotal($get, $set);
                        InvoiceResource::updateTotals($livewire->getOwnerRecord()->items(), $set); // This might need adjustment based on how totals are updated in parent
                    }),
                TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, RelationManager $livewire) {
                        $this->updateItemTotal($get, $set);
                        InvoiceResource::updateTotals($livewire->getOwnerRecord()->items(), $set); // This might need adjustment
                    }),
                TextInput::make('total_price')
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated()
                    ->required(),
            ])->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->money('usd') // Assuming USD, adjust if necessary
                    ->sortable(),
                TextColumn::make('total_price')
                    ->money('usd') // Assuming USD
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_price'] = ($data['quantity'] ?? 0) * ($data['unit_price'] ?? 0);
                        return $data;
                    })
                    ->after(function (RelationManager $livewire) {
                        // $livewire->emit('updateInvoiceTotals'); // Example of emitting event
                        // Or directly call the update method if accessible and appropriate
                        // This part is tricky as the Repeater in InvoiceResource handles totals directly.
                        // For now, we rely on the Repeater's logic. If standalone RM is used more, this needs refinement.
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['total_price'] = ($data['quantity'] ?? 0) * ($data['unit_price'] ?? 0);
                        return $data;
                    })
                    ->after(function (RelationManager $livewire) {
                        // Similar to CreateAction, handle total updates if needed
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function (RelationManager $livewire) {
                        // Similar to CreateAction, handle total updates if needed
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function updateItemTotal(Forms\Get $get, Forms\Set $set): void
    {
        $quantity = $get('quantity') ?? 0;
        $unitPrice = $get('unit_price') ?? 0;
        $set('total_price', $quantity * $unitPrice);
    }

    // Optional: If you need to react to changes and update the parent Invoice totals from here.
    // This is complex because the InvoiceResource uses a Repeater which has its own state management for totals.
    // If this Relation Manager is used on a different page (e.g., ViewInvoice), then this logic would be more critical.
    // For now, the primary way to manage items and totals is via the Repeater in InvoiceResource.

}
