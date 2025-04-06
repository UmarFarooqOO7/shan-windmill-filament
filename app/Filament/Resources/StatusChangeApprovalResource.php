<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusChangeApprovalResource\Pages;
use App\Filament\Resources\StatusChangeApprovalResource\Infolists\StatusChangeApprovalInfolist;
use App\Filament\Resources\StatusChangeApprovalResource\Tables\StatusChangeApprovalTable;
use App\Models\StatusChangeApproval;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;

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
        return StatusChangeApprovalInfolist::infolist($infolist);
    }

    public static function table(Table $table): Table
    {
        return StatusChangeApprovalTable::table($table);
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
