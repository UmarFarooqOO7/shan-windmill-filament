<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Pages;

use App\Filament\Resources\StatusChangeApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStatusChangeApprovals extends ListRecords
{
    protected static string $resource = StatusChangeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action needed since approvals are created by the system
        ];
    }
}
