<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Status;
use App\Models\StatusChangeApproval;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use App\Mail\LeadStatusApprovalRequestEmail; // Added for email notification
use Illuminate\Support\Facades\Mail; // Added for sending mail
use Illuminate\Support\Facades\Log; // Added for logging

class StatusChangeApprovalService
{
    /**
     * Checks if a status change requires approval and handles accordingly
     *
     * @param Lead $lead The lead being updated
     * @param int|null $statusId The new status ID
     * @param string $statusType The type of status (lead, setout, writ)
     * @param string|null $reason Optional reason for the status change
     * @param int|null $originalStatusId The original status ID before the change
     * @return bool Whether the status was changed immediately (true) or queued for approval (false)
     */
    public function handleStatusChange(Lead $lead, ?int $statusId, string $statusType, ?string $reason = null, ?int $originalStatusId = null): bool
    {
        // If status is null, allow it to be set directly (clearing a status doesn't need approval)
        if ($statusId === null) {
            return true;
        }

        // Get the status model for the new status ID
        $toStatusModel = Status::find($statusId); // Renamed to avoid confusion
        if (!$toStatusModel instanceof Status) {
            Log::warning("[StatusChangeApprovalService] handleStatusChange: toStatus model not found for ID {$statusId}. Allowing change.");
            return true; // If status not found, allow change (defensive)
        }

        // Determine which field stores this status type
        $statusField = match ($statusType) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => null
        };

        if (!$statusField) {
            Log::warning("[StatusChangeApprovalService] handleStatusChange: Unknown status type '{$statusType}'. Allowing change.");
            return true; // Unknown status type, allow the change (defensive)
        }

        $fromStatusModel = null;
        if ($originalStatusId) {
            $fromStatusModel = Status::find($originalStatusId);
        }

        // If status doesn't require approval, allow the change
        if (!$toStatusModel->requiresApproval()) {
            return true;
        }

        // Get current user
        $currentUser = Filament::auth()->user();
        if (!$currentUser) {
            Log::warning("[StatusChangeApprovalService] handleStatusChange: No authenticated user. Allowing change for lead {$lead->id}.");
            return true; // If no authenticated user, allow change (defensive)
        }

        // Admin users can bypass approval requirements
        if ($currentUser->is_admin) {
            return true;
        }

        // If the from and to status IDs are actually the same, allow the change without approval
        if ($originalStatusId === $statusId) {
            return true;
        }

        // Create a status change approval request
        StatusChangeApproval::create([
            'lead_id' => $lead->id,
            'requested_by' => $currentUser->id,
            'status_type' => $statusType,
            'from_status_id' => $originalStatusId, // Use $originalStatusId directly
            'to_status_id' => $statusId,
            'reason' => $reason,
        ]);

        // Notify admin users
        $this->notifyAdminsOfPendingApproval($lead, $currentUser, $toStatusModel, $statusType, $fromStatusModel, $reason);

        // Return false to indicate the status wasn't changed immediately
        return false;
    }

    /**
     * Notify admin users about a pending status change approval
     */
    private function notifyAdminsOfPendingApproval(Lead $lead, User $requester, Status $toStatus, string $statusType, ?Status $fromStatus, ?string $reason): void
    {
        // Format a user-friendly status type label
        $statusTypeLabel = ucfirst($statusType);
        $fromStatusName = $fromStatus ? $fromStatus->name : "None";

        // Create notification title and body for in-app notification
        $title = "Pending {$statusTypeLabel} Status Change";
        $body = "{$requester->name} requested to change Lead #{$lead->id} ({$lead->plaintiff}) {$statusTypeLabel} status from \"{$fromStatusName}\" to \"{$toStatus->name}\". This requires your approval.";
        if ($reason) {
            $body .= " Reason: " . $reason;
        }

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

            // Send email notification to admin users
            try {
                Mail::to($admin->email)->send(new LeadStatusApprovalRequestEmail($lead, $toStatus, $fromStatus, $requester, $reason));
            } catch (\Exception $e) {
                Log::error("[StatusChangeApprovalService] Failed to send LeadStatusApprovalRequestEmail to admin {$admin->email} for lead {$lead->id}: " . $e->getMessage());
            }
        }
    }
}
