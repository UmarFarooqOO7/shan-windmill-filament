<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Tables;

use App\Models\StatusChangeApproval;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions;
use Filament\Tables\Filters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StatusChangeApprovalTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('lead.plaintiff')
                    ->label('Lead')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->searchable()
                    ->sortable(),
                Columns\TextColumn::make('status_type')
                    ->label('Status Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'lead' => 'primary',
                        'setout' => 'success',
                        'writ' => 'warning',
                        default => 'gray',
                    }),
                Columns\TextColumn::make('fromStatus.name')
                    ->label('From')
                    ->default('NIL')
                    ->searchable(),
                Columns\TextColumn::make('toStatus.name')
                    ->label('To')
                    ->searchable(),
                Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->since()
                    ->sortable(),
                Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->since()
                    ->sortable(),
                Columns\TextColumn::make('rejected_at')
                    ->label('Rejected At')
                    ->since()
                    ->sortable(),
                Columns\IconColumn::make('is_pending')
                    ->label('Status')
                    ->getStateUsing(fn(StatusChangeApproval $record): bool => $record->isPending())
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->filters([
                Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function (Builder $query, $value) {
                            if ($value === 'pending') {
                                return $query->whereNull('approved_at')->whereNull('rejected_at');
                            } elseif ($value === 'approved') {
                                return $query->whereNotNull('approved_at');
                            } elseif ($value === 'rejected') {
                                return $query->whereNotNull('rejected_at');
                            }
                        });
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),

                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(StatusChangeApproval $record) => $record->isPending())
                    ->action(function (StatusChangeApproval $record) {
                        // Use the centralized service
                        $approvalService = app(\App\Services\StatusChangeApprovalActionService::class);
                        if ($approvalService->approveStatusChange($record)) {
                            // Success notification is already handled by the service
                        }
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn(StatusChangeApproval $record) => $record->isPending())
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (StatusChangeApproval $record, array $data) {
                        // Use the centralized service
                        $approvalService = app(\App\Services\StatusChangeApprovalActionService::class);
                        if ($approvalService->rejectStatusChange($record, $data['rejection_reason'])) {
                            // Success notification is already handled by the service
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
