<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Pages;

use App\Filament\Resources\StatusChangeApprovalResource;
use App\Filament\Resources\StatusChangeApprovalResource\Infolists\StatusChangeApprovalInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Auth;

class ViewStatusChangeApproval extends ViewRecord
{
    protected static string $resource = StatusChangeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check')
                ->visible(fn () => $this->record->isPending() && Auth::user()->is_admin)
                ->action(function () {
                    // Use the centralized service
                    $approvalService = app(\App\Services\StatusChangeApprovalActionService::class);
                    if ($approvalService->approveStatusChange($this->record)) {
                        $this->redirect(StatusChangeApprovalResource::getUrl('index'));
                    }
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->visible(fn () => $this->record->isPending() && Auth::user()->is_admin)
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Reason for Rejection')
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Use the centralized service
                    $approvalService = app(\App\Services\StatusChangeApprovalActionService::class);
                    if ($approvalService->rejectStatusChange($this->record, $data['rejection_reason'])) {
                        $this->redirect(StatusChangeApprovalResource::getUrl('index'));
                    }
                }),
        ];
    }
}
