<?php

namespace App\Services;

use App\Models\StatusChangeApproval;
use App\Models\Status; // Added for fetching Status model
use App\Models\User; // Added for fetching User model for approver
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail; // Added for sending mail
use Illuminate\Support\Facades\Log; // Added for logging
use App\Mail\LeadStatusApprovedEmail;
use App\Mail\LeadStatusRejectedEmail;

class StatusChangeApprovalActionService
{
    protected $notificationService;

    public function __construct(LeadStatusNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Approve a status change request
     *
     * @param StatusChangeApproval $record The status change approval record
     * @return bool Whether the approval was successful
     */
    public function approveStatusChange(StatusChangeApproval $record): bool
    {
        $lead = $record->lead;
        $requester = $record->requester; // User who requested the change
        $approvedByUser = Auth::user(); // Admin who is approving

        // Determine which field to update
        $statusField = match($record->status_type) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => null
        };

        if (!$statusField) {
            Log::error("[StatusChangeApprovalActionService] approveStatusChange: Invalid status_type '{$record->status_type}' for approval ID {$record->id}");
            return false;
        }

        // Update the lead status
        $lead->$statusField = $record->to_status_id;
        $lead->save();

        // Update the approval record
        $record->approved_by = $approvedByUser ? $approvedByUser->id : null;
        $record->approved_at = now();
        $record->save();

        // Send notification about the approved status change (in-app)
        $this->notificationService->notifyStatusChange($lead, $record->to_status_id, $record->status_type);

        // Notify the requester that their request was approved (in-app)
        if ($requester) {
            Notification::make()
                ->title('Status Change Approved')
                ->body("Your request to change the {$record->status_type} status for Lead #{$lead->id} ({$lead->plaintiff}) has been approved.")
                ->success()
                ->sendToDatabase($requester);

            // Send email to the requester
            $approvedStatusModel = Status::find($record->to_status_id);
            if ($approvedStatusModel && $approvedByUser) {
                try {
                    Mail::to($requester->email)->send(new LeadStatusApprovedEmail($lead, $approvedStatusModel, $approvedByUser));
                } catch (\Exception $e) {
                    Log::error("[StatusChangeApprovalActionService] Failed to send LeadStatusApprovedEmail to requester {$requester->email} for lead {$lead->id}: " . $e->getMessage());
                }
            }
        }

        // Notify the current user (admin who approved) (in-app)
        if ($approvedByUser) {
            Notification::make()
                ->title('Status Change Approved')
                ->body("Status change request for Lead #{$lead->id} ({$lead->plaintiff}) has been approved successfully.")
                ->success()
                ->send(); // Sends to current Filament user
        }

        return true;
    }

    /**
     * Reject a status change request
     *
     * @param StatusChangeApproval $record The status change approval record
     * @param string $rejectionReason The reason for rejection
     * @return bool Whether the rejection was successful
     */
    public function rejectStatusChange(StatusChangeApproval $record, string $rejectionReason): bool
    {
        $lead = $record->lead;
        $requester = $record->requester;
        $rejectedByUser = Auth::user(); // Admin who is rejecting

        // Update the approval record
        $record->approved_by = $rejectedByUser ? $rejectedByUser->id : null;
        $record->rejected_at = now();
        $record->rejection_reason = $rejectionReason;
        $record->save();

        // Notify the requester that their request was rejected (in-app)
        if ($requester) {
            Notification::make()
                ->title('Status Change Rejected')
                ->body("Your request to change the {$record->status_type} status for Lead #{$lead->id} ({$lead->plaintiff}) was rejected: {$rejectionReason}")
                ->danger()
                ->sendToDatabase($requester);

            // Send email to the requester
            $rejectedStatusModel = Status::find($record->to_status_id); // This is the status that was requested but now rejected
            if ($rejectedStatusModel && $rejectedByUser) {
                try {
                    Mail::to($requester->email)->send(new LeadStatusRejectedEmail($lead, $rejectedStatusModel, $rejectedByUser, $rejectionReason));
                } catch (\Exception $e) {
                    Log::error("[StatusChangeApprovalActionService] Failed to send LeadStatusRejectedEmail to requester {$requester->email} for lead {$lead->id}: " . $e->getMessage());
                }
            }
        }

        // Notify the current user (admin who rejected) (in-app)
        if ($rejectedByUser) {
            Notification::make()
                ->title('Status Change Rejected')
                ->body("Status change request for Lead #{$lead->id} ({$lead->plaintiff}) has been rejected.")
                ->danger()
                ->send(); // Sends to current Filament user
        }

        return true;
    }
}
