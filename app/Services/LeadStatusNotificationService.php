<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

class LeadStatusNotificationService
{
    /**
     * Send notification for lead status changes
     *
     * @param Lead $lead The lead that had its status changed
     * @param int|null $statusId The new status ID (can be null)
     * @param string $statusType The type of status (lead, setout, writ)
     * @param string|null $columnName The column name that was updated
     * @return void
     */
    public function notifyStatusChange(Lead $lead, ?int $statusId, string $statusType = 'lead', ?string $columnName = null): void
    {
        // Get current user who made the change
        $currentUser = Filament::auth()->user();
        if (!$currentUser) {
            return;
        }

        // Determine which field changed based on status type
        $statusField = match($statusType) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => $columnName ?? 'status_id'
        };

        // Get new status name
        $statusName = "Not Set";
        if ($statusId) {
            $status = Status::find($statusId);
            if ($status) {
                $statusName = $status->name;
            }
        }

        // Get previous status name if available
        $previousStatusName = "Not Set";
        if ($lead->wasChanged($statusField)) {
            $previousStatusId = $lead->getOriginal($statusField);
            if ($previousStatusId) {
                $previousStatus = Status::find($previousStatusId);
                if ($previousStatus) {
                    $previousStatusName = $previousStatus->name;
                }
            }
        }

        // Format a user-friendly status type label
        $statusTypeLabel = ucfirst($statusType);

        // Create notification title and body
        $title = "{$statusTypeLabel} Status Updated";
        $body = "Lead #{$lead->id} ({$lead->plaintiff}) {$statusTypeLabel} status changed from \"{$previousStatusName}\" to \"{$statusName}\" by {$currentUser->name}";

        // Send notifications to admin users
        $adminUsers = User::where('is_admin', true)->get();
        foreach ($adminUsers as $admin) {
            // Skip sending notification to the current user if they're an admin
            if ($currentUser->is_admin && $admin->id === $currentUser->id) {
                continue;
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->success()
                ->sendToDatabase($admin);
        }
    }
}
