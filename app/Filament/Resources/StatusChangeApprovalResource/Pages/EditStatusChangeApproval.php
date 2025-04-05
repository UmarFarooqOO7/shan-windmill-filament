<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Pages;

use App\Filament\Resources\StatusChangeApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStatusChangeApproval extends EditRecord
{
    protected static string $resource = StatusChangeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
