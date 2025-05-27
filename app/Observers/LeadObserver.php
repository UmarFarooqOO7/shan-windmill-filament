<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\Event; // Add this line
use App\Services\LeadStatusNotificationService;
use App\Services\StatusChangeApprovalService;
use Illuminate\Support\Carbon; // Add this line
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        $this->syncLeadSetoutEvent($lead);
    }

    /**
     * Handle the Lead "updating" event.
     */
    public function updating(Lead $lead): void
    {
        // Get the form data
        $formData = $this->getFormData();

        // Check if status_id has been changed
        if ($lead->isDirty('status_id')) {
            $newStatusId = $lead->status_id;
            $originalStatusId = $lead->getOriginal('status_id');
            $reason = $formData['status_reason'] ?? null;

            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'lead', $reason, $originalStatusId)) {
                // If approval is required, revert the status change
                $lead->status_id = $originalStatusId;

                // Notify user that the change requires approval
                $this->notifyUserOfPendingApproval($lead, 'lead');
            }
        }

        // Check if setout_id has been changed
        if ($lead->isDirty('setout_id')) {
            $newStatusId = $lead->setout_id;
            $originalStatusId = $lead->getOriginal('setout_id');
            $reason = $formData['setout_status_reason'] ?? null;

            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'setout', $reason, $originalStatusId)) {
                // If approval is required, revert the status change
                $lead->setout_id = $originalStatusId;

                // Notify user that the change requires approval
                $this->notifyUserOfPendingApproval($lead, 'setout');
            }
        }

        // Check if writ_id has been changed
        if ($lead->isDirty('writ_id')) {
            $newStatusId = $lead->writ_id;
            $originalStatusId = $lead->getOriginal('writ_id');
            $reason = $formData['writ_status_reason'] ?? null;

            // Check if the status change requires approval
            if (!$this->approvalService->handleStatusChange($lead, $newStatusId, 'writ', $reason, $originalStatusId)) {
                // If approval is required, revert the status change
                $lead->writ_id = $originalStatusId;

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
        $this->syncLeadSetoutEvent($lead);

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
     * Handle the Lead "deleting" event.
     * This method is called BEFORE the lead is actually deleted from the database.
     * We use this to delete associated events, which will in turn trigger
     * the EventObserver to remove them from Google Calendar.
     */
    public function deleting(Lead $lead): void
    {
        // Log::info('Lead deleting hook: Attempting to delete associated events.', ['lead_id' => $lead->id]);

        // Find and delete all events associated with this lead
        $events = Event::where('lead_id', $lead->id)->get();

        if ($events->isNotEmpty()) {
            // Log::info('Lead deleting hook: Found events to delete.', [
            //     'lead_id' => $lead->id,
            //     'event_ids' => $events->pluck('id')->toArray()
            // ]);
            foreach ($events as $event) {
                // Log::info('Lead deleting hook: Deleting event.', [
                //     'lead_id' => $lead->id,
                //     'event_id' => $event->id
                // ]);
                // Deleting the event here will trigger the EventObserver's "deleted" hook
                // which should handle the Google Calendar event deletion.
                $event->delete();
            }
        } else {
            // Log::info('Lead deleting hook: No associated events found to delete.', ['lead_id' => $lead->id]);
        }
    }

    /**
     * Synchronize the setout event for the lead.
     */
    protected function syncLeadSetoutEvent(Lead $lead): void
    {
        // If setout_date is null, delete any existing setout event
        if (is_null($lead->setout_date)) {
            if ($lead->setoutEvent) {
                $lead->setoutEvent->delete();
            }
            return;
        }

        // Prepare event data
        $setoutDateTime = Carbon::parse($lead->setout_date);
        if ($lead->setout_time) {
            try {
                $time = Carbon::parse($lead->setout_time);
                $setoutDateTime->setTime($time->hour, $time->minute, $time->second);
            } catch (\Exception $e) {
                // Default to 9 AM if time is invalid
                $setoutDateTime->startOfDay()->hour(9);
            }
        } else {
            // Default to 9 AM if time is not set
            $setoutDateTime->startOfDay()->hour(9);
        }

        $eventData = [
            'title' => 'Setout: ' . ($lead->plaintiff ?? 'N/A') . ' vs ' . ($lead->defendant_first_name ?? 'N/A'),
            'start_at' => $setoutDateTime,
            'end_at' => $setoutDateTime, // Or adjust if setouts can have a duration
            'all_day' => false,
            'is_lead_setout' => true,
            'user_id' => Auth::id(),
        ];

        // Update or create the setout event
        Event::updateOrCreate(
            ['lead_id' => $lead->id, 'is_lead_setout' => true], // Find by lead_id and is_lead_setout flag
            $eventData
        );
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

    /**
     * Get the form data
     */
    private function getFormData(): array
    {
        return request()->all();
    }
}
