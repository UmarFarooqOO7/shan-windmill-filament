<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Infolists;

use App\Models\StatusChangeApproval;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class StatusChangeApprovalInfolist
{

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Status Change Request')
                    ->schema([
                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('status_type')
                                    ->label('Status Type')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'lead' => 'primary',
                                        'setout' => 'success',
                                        'writ' => 'warning',
                                        default => 'gray',
                                    }),
                                Components\TextEntry::make('fromStatus.name')
                                    ->label('From Status')
                                    ->default('NIL'),
                                Components\TextEntry::make('toStatus.name')
                                    ->label('To Status'),
                            ]),

                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('requester.name')
                                    ->label('Requested By'),
                                // Components\TextEntry::make('reason')
                                //     ->label('Reason for Change')
                                //     ->columnSpan(2),
                            ]),

                        Components\Grid::make(3)
                            ->schema([
                                Components\TextEntry::make('created_at')
                                    ->label('Requested At')
                                    ->dateTime(),

                                Components\TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->dateTime(),

                                Components\TextEntry::make('rejected_at')
                                    ->label('Rejected At')
                                    ->dateTime(),
                            ]),
                    ]),

                Components\Section::make('Lead Information')
                    ->schema([
                        Components\Grid::make(2)
                            ->schema([
                                Components\TextEntry::make('lead.id')
                                    ->label('Lead ID'),
                                Components\TextEntry::make('lead.plaintiff')
                                    ->label('Plaintiff'),
                                Components\TextEntry::make('lead.defendant_first_name')
                                    ->label('Defendant First Name'),
                                Components\TextEntry::make('lead.defendant_last_name')
                                    ->label('Defendant Last Name'),
                                Components\TextEntry::make('lead.case_number')
                                    ->label('Case Number'),
                                Components\TextEntry::make('lead.address')
                                    ->label('Address'),
                                Components\TextEntry::make('lead.city')
                                    ->label('City'),
                                Components\TextEntry::make('lead.state')
                                    ->label('State'),
                                Components\TextEntry::make('lead.setout_date')
                                    ->label('Setout Date')
                                    ->date(),
                                Components\TextEntry::make('lead.setout_time')
                                    ->label('Setout Time'),
                            ]),
                    ]),
            ]);
    }
}
