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
     * @param int $statusId The new status ID
     * @param string $statusType The type of status (lead, setout, writ)
     * @param string|null $columnName The column name that was updated
     * @return void
     */
    public function notifyStatusChange(Lead $lead, int $statusId, string $statusType = 'lead', ?string $columnName = null): void
    {
        // Get current user who made the change
        $currentUser = Filament::auth()->user();
        if (!$currentUser) {
            return;
        }

        // Get the status name for the notification
        $status = Status::find($statusId);
        if (!$status) {
            return;
        }

        // Determine which field changed based on status type
        $statusField = match($statusType) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => $columnName ?? 'status_id'
        };

        // Format a user-friendly status type label
        $statusTypeLabel = ucfirst($statusType);

        // Create notification title and body
        $title = "{$statusTypeLabel} Status Updated";
        $body = "Lead #{$lead->id} ({$lead->plaintiff}) {$statusTypeLabel} status changed to \"{$status->name}\" by {$currentUser->name}";

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