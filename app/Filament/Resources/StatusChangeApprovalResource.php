<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusChangeApprovalResource\Pages;
use App\Models\StatusChangeApproval;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
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
        return static::getModel()::where(function($query) {
            $query->whereNull('approved_at')->whereNull('rejected_at');
        })->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $count = static::getModel()::where(function($query) {
            $query->whereNull('approved_at')->whereNull('rejected_at');
        })->count();

        return $count > 0 ? 'warning' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Status Change Request')
                    ->schema([
                        Forms\Components\TextInput::make('lead.plaintiff')
                            ->label('Lead Plaintiff')
                            ->disabled(),
                        Forms\Components\TextInput::make('requester.name')
                            ->label('Requested By')
                            ->disabled(),
                        Forms\Components\TextInput::make('status_type')
                            ->label('Status Type')
                            ->disabled(),
                        Forms\Components\TextInput::make('fromStatus.name')
                            ->label('From Status')
                            ->disabled(),
                        Forms\Components\TextInput::make('toStatus.name')
                            ->label('To Status')
                            ->disabled(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Change')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Requested At')
                            ->disabled(),
                    ]),
                
                Forms\Components\Section::make('Approval Decision')
                    ->schema([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason (leave empty to approve)')
                            ->required(false),
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
                    ->color(fn (string $state): string => match ($state) {
                        'lead' => 'primary',
                        'setout' => 'success',
                        'writ' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('fromStatus.name')
                    ->label('From')
                    ->searchable(),
                Tables\Columns\TextColumn::make('toStatus.name')
                    ->label('To')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rejected_at')
                    ->label('Rejected At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_pending')
                    ->label('Status')
                    ->getStateUsing(fn (StatusChangeApproval $record): bool => $record->isPending())
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
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (StatusChangeApproval $record) => $record->isPending())
                    ->action(function (StatusChangeApproval $record) {
                        // Call service to approve the status change
                        $service = new \App\Services\LeadStatusNotificationService();
                        $lead = $record->lead;
                        
                        // Determine which field to update
                        $statusField = match($record->status_type) {
                            'lead' => 'status_id',
                            'setout' => 'setout_id',
                            'writ' => 'writ_id',
                            default => null
                        };
                        
                        if ($statusField) {
                            // Update the lead status
                            $lead->$statusField = $record->to_status_id;
                            $lead->save();
                            
                            // Update the approval record
                            $record->approved_by = Auth::id();
                            $record->approved_at = now();
                            $record->save();
                            
                            // Send notification about the approved status change
                            $service->notifyStatusChange($lead, $record->to_status_id, $record->status_type);
                            
                            // Notify the requester that their request was approved
                            \Filament\Notifications\Notification::make()
                                ->title('Status Change Approved')
                                ->body("Your request to change the {$record->status_type} status has been approved.")
                                ->success()
                                ->sendToDatabase($record->requester);
                        }
                    }),
                
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (StatusChangeApproval $record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->required(),
                    ])
                    ->action(function (StatusChangeApproval $record, array $data) {
                        // Update the approval record
                        $record->approved_by = Auth::id();
                        $record->rejected_at = now();
                        $record->rejection_reason = $data['rejection_reason'];
                        $record->save();
                        
                        // Notify the requester that their request was rejected
                        \Filament\Notifications\Notification::make()
                            ->title('Status Change Rejected')
                            ->body("Your request to change the {$record->status_type} status was rejected: {$data['rejection_reason']}")
                            ->danger()
                            ->sendToDatabase($record->requester);
                    }),
                
                Tables\Actions\ViewAction::make(),
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
