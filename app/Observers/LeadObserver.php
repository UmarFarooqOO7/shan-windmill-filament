<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\LeadStatusNotificationService;
use App\Services\StatusChangeApprovalService;

class LeadObserver
{
    protected $notificationService;
    protected $approvalService;

    public function __construct(
        LeadStatusNotificationService $notificationService,
        StatusChangeApprovalService $approvalService
    ) {
        $this->notificationService = $notificationService;
        $this->approvalService = $approvalService;
    }

    /**
     * Handle the Lead "updating" event.
     */
    public function updating(Lead $lead): void
    {
        // Check if status_id has been changed
        if ($lead->isDirty('status_id')) {
            $newStatusId = $lead->status_id;
            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'lead')) {
                // If approval is required, revert the status change
                $lead->status_id = $lead->getOriginal('status_id');

                // Notify user that the change requires approval
                $this->notifyUserOfPendingApproval($lead, 'lead');
            }
        }

        // Check if setout_id has been changed
        if ($lead->isDirty('setout_id')) {
            $newStatusId = $lead->setout_id;
            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'setout')) {
                // If approval is required, revert the status change
                $lead->setout_id = $lead->getOriginal('setout_id');

                // Notify user that the change requires approval
                $this->notifyUserOfPendingApproval($lead, 'setout');
            }
        }

        // Check if writ_id has been changed
        if ($lead->isDirty('writ_id')) {
            $newStatusId = $lead->writ_id;
            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'writ')) {
                // If approval is required, revert the status change
                $lead->writ_id = $lead->getOriginal('writ_id');

                // Notify user that the change requires approval
                $this->notifyUserOfPendingApproval($lead, 'writ');
            }
        }
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Check if status_id has been changed - notify even for null values
        if ($lead->wasChanged('status_id')) {
            $this->notificationService->notifyStatusChange($lead, $lead->status_id, 'lead');
        }

        // Check if setout_id has been changed - notify even for null values
        if ($lead->wasChanged('setout_id')) {
            $this->notificationService->notifyStatusChange($lead, $lead->setout_id, 'setout');
        }

        // Check if writ_id has been changed - notify even for null values
        if ($lead->wasChanged('writ_id')) {
            $this->notificationService->notifyStatusChange($lead, $lead->writ_id, 'writ');
        }
    }

    /**
     * Notify the current user that their status change requires approval
     */
    private function notifyUserOfPendingApproval(Lead $lead, string $statusType): void
    {
        $user = \Filament\Facades\Filament::auth()->user();
        if (!$user) {
            return;
        }

        $statusTypeLabel = ucfirst($statusType);

        \Filament\Notifications\Notification::make()
            ->title("Status Change Pending Approval")
            ->body("Your request to change the {$statusTypeLabel} status for Lead #{$lead->id} requires admin approval. You'll be notified once it's approved or rejected.")
            ->warning()
            ->send();
    }
}
