<?php

namespace App\Services;

use App\Models\StatusChangeApproval;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

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
        
        // Determine which field to update
        $statusField = match($record->status_type) {
            'lead' => 'status_id',
            'setout' => 'setout_id',
            'writ' => 'writ_id',
            default => null
        };
        
        if (!$statusField) {
            return false;
        }
        
        // Update the lead status
        $lead->$statusField = $record->to_status_id;
        $lead->save();
        
        // Update the approval record
        $record->approved_by = Auth::id();
        $record->approved_at = now();
        $record->save();
        
        // Send notification about the approved status change
        $this->notificationService->notifyStatusChange($lead, $record->to_status_id, $record->status_type);
        
        // Notify the requester that their request was approved
        Notification::make()
            ->title('Status Change Approved')
            ->body("Your request to change the {$record->status_type} status for Lead #{$lead->id} ({$lead->plaintiff}) has been approved.")
            ->success()
            ->sendToDatabase($record->requester);
            
        // Notify the current user
        Notification::make()
            ->title('Status Change Approved')
            ->body("Status change request for Lead #{$lead->id} ({$lead->plaintiff}) has been approved successfully.")
            ->success()
            ->send();
            
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
        
        // Update the approval record
        $record->approved_by = Auth::id();
        $record->rejected_at = now();
        $record->rejection_reason = $rejectionReason;
        $record->save();
        
        // Notify the requester that their request was rejected
        Notification::make()
            ->title('Status Change Rejected')
            ->body("Your request to change the {$record->status_type} status for Lead #{$lead->id} ({$lead->plaintiff}) was rejected: {$rejectionReason}")
            ->danger()
            ->sendToDatabase($record->requester);
            
        // Notify the current user
        Notification::make()
            ->title('Status Change Rejected')
            ->body("Status change request for Lead #{$lead->id} ({$lead->plaintiff}) has been rejected.")
            ->danger()
            ->send();
            
        return true;
    }
}