<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Status;
use App\Models\StatusChangeApproval;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class StatusChangeApprovalService
{
    /**
     * Checks if a status change requires approval and handles accordingly
     *
     * @param Lead $lead The lead being updated
     * @param int|null $statusId The new status ID
     * @param string $statusType The type of status (lead, setout, writ)
     * @param string|null $reason Optional reason for the status change
     * @return bool Whether the status was changed immediately (true) or queued for approval (false)
     */
    public function handleStatusChange(Lead $lead, ?int $statusId, string $statusType, ?string $reason = null): bool
    {
        // If status is null, allow it to be set directly (clearing a status doesn't need approval)
        if ($statusId === null) {
            return true;
        }

        // Get the status
        $status = Status::find($statusId);
        if (!$status) {
            return true; // If status not found, allow change (defensive)
        }

        // If status doesn't require approval, allow the change
        if (!$status->requiresApproval()) {
            return true;
        }

        // Get current user
        $currentUser = Filament::auth()->user();
        if (!$currentUser) {
            return true; // If no authenticated user, allow change (defensive)
        }

        // Admin users can bypass approval requirements
        if ($currentUser->is_admin) {
            return true;
        }

        // Determine which field stores this status type
        $statusField = match($statusType) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => null
        };

        if (!$statusField) {
            return true; // Unknown status type, allow the change (defensive)
        }

        // Get the current status ID (which will be the "from" status)
        $fromStatusId = $lead->$statusField;

        // Create a status change approval request
        StatusChangeApproval::create([
            'lead_id' => $lead->id,
            'requested_by' => $currentUser->id,
            'status_type' => $statusType,
            'from_status_id' => $fromStatusId,
            'to_status_id' => $statusId,
            'reason' => $reason,
        ]);

        // Notify admin users
        $this->notifyAdminsOfPendingApproval($lead, $currentUser, $status, $statusType);

        // Return false to indicate the status wasn't changed immediately
        return false;
    }

    /**
     * Notify admin users about a pending status change approval
     */
    private function notifyAdminsOfPendingApproval(Lead $lead, User $requester, Status $toStatus, string $statusType): void
    {
        // Format a user-friendly status type label
        $statusTypeLabel = ucfirst($statusType);

        // Create notification title and body
        $title = "Pending {$statusTypeLabel} Status Change";
        $body = "{$requester->name} requested to change Lead #{$lead->id} ({$lead->plaintiff}) {$statusTypeLabel} status to \"{$toStatus->name}\". This requires your approval.";

        // Send notifications to admin users
        $adminUsers = User::where('is_admin', true)->get();
        foreach ($adminUsers as $admin) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->button()
                        ->url(route('filament.admin.resources.status-change-approvals.index'))
                ])
                ->warning()
                ->sendToDatabase($admin);
        }
    }
}
