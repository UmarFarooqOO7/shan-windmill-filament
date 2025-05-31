<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Status;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use App\Mail\LeadStatusChangedEmail; // Added for email notification
use Illuminate\Support\Facades\Mail; // Added for sending mail
use Illuminate\Support\Facades\Log; // Added for logging

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
            // If no current user (e.g. system process), try to get lead owner or a default admin
            // This part might need adjustment based on your business logic for who should be notified
            // For now, we'll log and return if no user context is found for "changedBy"
            Log::warning("LeadStatusNotificationService: Cannot determine user who changed status for Lead #{$lead->id}. Email notification for status change might be incomplete.");
            // Potentially, you could assign a system user or skip the changedBy part in email.
            // For now, we require a $currentUser to proceed with the original logic.
            // If you want to send emails even without a $currentUser, this logic needs to be adapted.
            return; 
        }

        // Determine which field changed based on status type
        $statusField = match($statusType) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => $columnName ?? 'status_id'
        };

        $newStatusModel = null;
        $statusName = "Not Set";
        if ($statusId) {
            $newStatusModel = Status::find($statusId);
            if ($newStatusModel) {
                $statusName = $newStatusModel->name;
            }
        }

        $previousStatusModel = null;
        $previousStatusName = "Not Set";
        if ($lead->wasChanged($statusField)) {
            $previousStatusId = $lead->getOriginal($statusField);
            if ($previousStatusId) {
                $previousStatusModel = Status::find($previousStatusId);
                if ($previousStatusModel) {
                    $previousStatusName = $previousStatusModel->name;
                }
            }
        }

        // Format a user-friendly status type label
        $statusTypeLabel = ucfirst($statusType);

        // Create notification title and body for in-app notification
        $title = "{$statusTypeLabel} Status Updated";
        $body = "Lead #{$lead->id} ({$lead->plaintiff}) {$statusTypeLabel} status changed from \"{$previousStatusName}\" to \"{$statusName}\" by {$currentUser->name}";

        // Send in-app notifications to admin users
        $adminUsers = User::where('is_admin', true)->get();
        foreach ($adminUsers as $admin) {
            // Skip sending notification to the current user if they're an admin
            if ($currentUser->is_admin && $admin->id === $currentUser->id) {
                // continue; // We might still want admins to receive emails even if they made the change
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->success()
                ->sendToDatabase($admin);
            
            // Send email notification to admin users
            try {
                Mail::to($admin->email)->send(new LeadStatusChangedEmail($lead, $newStatusModel, $previousStatusModel, $currentUser));
            } catch (\Exception $e) {
                Log::error("Failed to send LeadStatusChangedEmail to admin {$admin->email} for lead {$lead->id}: " . $e->getMessage());
            }
        }
    }
}
