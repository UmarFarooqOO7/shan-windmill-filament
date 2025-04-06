<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\LeadStatusNotificationService;

class LeadObserver
{
    protected $notificationService;

    public function __construct(LeadStatusNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        // Check if status_id has been changed
        if ($lead->isDirty('status_id') && $lead->status_id) {
            $this->notificationService->notifyStatusChange($lead, $lead->status_id, 'lead');
        }

        // Check if setout_id has been changed
        if ($lead->isDirty('setout_id') && $lead->setout_id) {
            $this->notificationService->notifyStatusChange($lead, $lead->setout_id, 'setout');
        }

        // Check if writ_id has been changed
        if ($lead->isDirty('writ_id') && $lead->writ_id) {
            $this->notificationService->notifyStatusChange($lead, $lead->writ_id, 'writ');
        }
    }
}
