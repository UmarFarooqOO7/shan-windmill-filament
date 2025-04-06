<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Pages;

use App\Filament\Resources\StatusChangeApprovalResource;
use App\Models\StatusChangeApproval;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStatusChangeApprovals extends ListRecords
{
    protected static string $resource = StatusChangeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action needed since approvals are created by the system
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('approved_at')),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('rejected_at')),
            'pending' => Tab::make('Pending')
                ->badge(StatusChangeApproval::query()->whereNull('approved_at')->whereNull('rejected_at')->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('approved_at')->whereNull('rejected_at')),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'pending';
    }
}
