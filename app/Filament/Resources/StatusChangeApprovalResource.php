<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusChangeApprovalResource\Pages;
use App\Models\StatusChangeApproval;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StatusChangeApprovalResource extends Resource
{
    protected static ?string $model = StatusChangeApproval::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Lead Management';
    protected static ?string $navigationLabel = 'Pending Approvals';
    protected static ?int $navigationSort = 100;

    // Only show this resource to admin users
    public static function canAccess(): bool
    {
        return auth()->user()->is_admin;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where(function ($query) {
            $query->whereNull('approved_at')->whereNull('rejected_at');
        })->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where(function ($query) {
            $query->whereNull('approved_at')->whereNull('rejected_at');
        })->count();

        return $count > 0 ? 'warning' : 'success';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status Change Request')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('status_type')
                                    ->label('Status Type')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'lead' => 'primary',
                                        'setout' => 'success',
                                        'writ' => 'warning',
                                        default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('fromStatus.name')
                                    ->label('From Status')
                                    ->default('NIL'),
                                Infolists\Components\TextEntry::make('toStatus.name')
                                    ->label('To Status'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('requester.name')
                                    ->label('Requested By'),
                                Infolists\Components\TextEntry::make('reason')
                                    ->label('Reason for Change')
                                    ->columnSpan(2),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Requested At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('rejected_at')
                                    ->label('Rejected At')
                                    ->dateTime(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Lead Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('lead.id')
                                    ->label('Lead ID'),
                                Infolists\Components\TextEntry::make('lead.plaintiff')
                                    ->label('Plaintiff'),
                                Infolists\Components\TextEntry::make('lead.defendant_first_name')
                                    ->label('Defendant First Name'),
                                Infolists\Components\TextEntry::make('lead.defendant_last_name')
                                    ->label('Defendant Last Name'),
                                Infolists\Components\TextEntry::make('lead.case_number')
                                    ->label('Case Number'),
                                Infolists\Components\TextEntry::make('lead.address')
                                    ->label('Address'),
                                Infolists\Components\TextEntry::make('lead.city')
                                    ->label('City'),
                                Infolists\Components\TextEntry::make('lead.state')
                                    ->label('State'),
                                Infolists\Components\TextEntry::make('lead.setout_date')
                                    ->label('Setout Date')
                                    ->date(),
                                Infolists\Components\TextEntry::make('lead.setout_time')
                                    ->label('Setout Time'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('lead.plaintiff')
                    ->label('Lead')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requested By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_type')
                    ->label('Status Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'lead' => 'primary',
                        'setout' => 'success',
                        'writ' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('From')
                    ->default('NIL')
                    ->searchable(),
                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('To')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejected_at')
                    ->label('Rejected At')
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_pending')
                    ->label('Status')
                    ->getStateUsing(fn(StatusChangeApproval $record): bool => $record->isPending())
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
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
                Tables\Actions\ViewAction::make(),

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
                        Forms\Components\Textarea::make('rejection_reason')
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatusChangeApprovals::route('/'),
            'view' => Pages\ViewStatusChangeApproval::route('/{record}'),
        ];
    }
}
