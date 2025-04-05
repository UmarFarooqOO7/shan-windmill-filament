<?php

namespace App\Filament\Resources\StatusChangeApprovalResource\Pages;

use App\Filament\Resources\StatusChangeApprovalResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

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
                ->visible(fn () => $this->record->isPending() && auth()->user()->is_admin)
                ->action(function () {
                    // Call service to approve the status change
                    $service = new \App\Services\LeadStatusNotificationService();
                    $lead = $this->record->lead;

                    // Determine which field to update
                    $statusField = match($this->record->status_type) {
                        'lead' => 'status_id',
                        'setout' => 'setout_id',
                        'writ' => 'writ_id',
                        default => null
                    };

                    if ($statusField) {
                        // Update the lead status
                        $lead->$statusField = $this->record->to_status_id;
                        $lead->save();

                        // Update the approval record
                        $this->record->approved_by = auth()->id();
                        $this->record->approved_at = now();
                        $this->record->save();

                        // Send notification about the approved status change
                        $service->notifyStatusChange($lead, $this->record->to_status_id, $this->record->status_type);

                        // Notify the requester that their request was approved
                        \Filament\Notifications\Notification::make()
                            ->title('Status Change Approved')
                            ->body("Status change request has been approved.")
                            ->success()
                            ->send();

                        // Notify the requester through the database
                        \Filament\Notifications\Notification::make()
                            ->title('Status Change Approved')
                            ->body("Your request to change the {$this->record->status_type} status has been approved.")
                            ->success()
                            ->sendToDatabase($this->record->requester);
                    }

                    $this->redirect(StatusChangeApprovalResource::getUrl('index'));
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->visible(fn () => $this->record->isPending() && auth()->user()->is_admin)
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Reason for Rejection')
                        ->required(),
                ])
                ->action(function (array $data) {
                    // Update the approval record
                    $this->record->approved_by = auth()->id();
                    $this->record->rejected_at = now();
                    $this->record->rejection_reason = $data['rejection_reason'];
                    $this->record->save();

                    // Notify the requester that their request was rejected
                    \Filament\Notifications\Notification::make()
                        ->title('Status Change Rejected')
                        ->body("Status change request has been rejected.")
                        ->danger()
                        ->send();

                    // Notify the requester through the database
                    \Filament\Notifications\Notification::make()
                        ->title('Status Change Rejected')
                        ->body("Your request to change the {$this->record->status_type} status was rejected: {$data['rejection_reason']}")
                        ->danger()
                        ->sendToDatabase($this->record->requester);

                    $this->redirect(StatusChangeApprovalResource::getUrl('index'));
                }),
        ];
    }
}
