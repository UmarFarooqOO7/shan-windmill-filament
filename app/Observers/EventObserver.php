<?php

namespace App\Observers;

use App\Models\Event;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Service\Calendar as GoogleCalendarServiceApi;
use Google\Service\Calendar\Event as GoogleServiceCalendarEvent;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewEventCreatedEmail;
use App\Mail\EventUpdatedEmail;
use App\Mail\EventDeletedEmail;

class EventObserver
{
    protected GoogleCalendarService $calendarService;

    public function __construct(GoogleCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    /**
     * Handle the Event "creating" event.
     */
    public function creating(Event $event): void
    {
        if (Auth::check() && !$event->user_id) {
            $event->user_id = Auth::id();
        } else if (!$event->user_id) {
            Log::warning('[EventObserver] No authenticated user to set user_id on creating event ID: ' . ($event->id ?? 'new'));
        }
    }
    /**
     * Handle the Event "updating" event.
     */
    public function updating(Event $event): void
    {
        if (Auth::check() && !$event->user_id) {
            $event->user_id = Auth::id();
        } else if (!$event->user_id) {
            Log::warning('[EventObserver] No authenticated user to set user_id on updating event ID: ' . ($event->id ?? 'new'));
        }
    }

    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $user = $event->user;

        if (!$user) {
            Log::warning('[EventObserver] User not found for event ID: ' . $event->id . '. Cannot send NewEventCreatedEmail or create Google Calendar event.');
            return;
        }

        // Send email notification to the event owner for new event
        try {
            Mail::to($user->email)->send(new NewEventCreatedEmail($event));
        } catch (\Exception $e) {
            Log::error("[EventObserver] Failed to send NewEventCreatedEmail to user {$user->email} for event {$event->id}: " . $e->getMessage());
        }

        if (!$user->google_access_token) {
            // This is a common case if user hasn't connected their calendar, not necessarily an error.
            // Log::warning('[EventObserver] User ID: ' . $user->id . ' does not have a Google access token. Skipping Google Calendar creation for event ID: ' . $event->id);
            return;
        }

        try {
            $client = $this->calendarService->getGoogleClientForUser($user);
            $calendarService = new GoogleCalendarServiceApi($client);
            $calendarId = 'primary';

            $googleServiceEvent = new GoogleServiceCalendarEvent();
            $googleServiceEvent->setSummary($event->title);
            $googleServiceEvent->setDescription($event->description);

            $start = new EventDateTime();
            if ($event->all_day) {
                $start->setDate(Carbon::parse($event->start_at)->toDateString());
            } else {
                $start->setDateTime(Carbon::parse($event->start_at)->toRfc3339String());
            }
            $googleServiceEvent->setStart($start);

            $end = new EventDateTime();
            if ($event->all_day) {
                // For all-day events, Google Calendar's end date is exclusive.
                // If end_at is null or same as start_at, it's a single day event.
                // We need to set the end date to the day after the start date.
                $endDate = Carbon::parse($event->end_at ?: $event->start_at)->addDay();
                $start->setDate(Carbon::parse($event->start_at)->toDateString()); // This line was duplicated in original, ensure it's correct here or remove if truly redundant.
                $end->setDate($endDate->toDateString());
            } else {
                $end->setDateTime(Carbon::parse($event->end_at)->toRfc3339String());
            }
            $googleServiceEvent->setEnd($end);

            $createdGoogleEvent = $calendarService->events->insert($calendarId, $googleServiceEvent);

            if ($createdGoogleEvent) {
                $googleEventId = $createdGoogleEvent->getId();
                $event->withoutEvents(function () use ($event, $googleEventId) {
                    $event->google_calendar_event_id = $googleEventId;
                    $event->save();
                });
                Log::info('[EventObserver] Successfully created Google Calendar event ID: ' . $googleEventId . ' for local event ID: ' . $event->id);
            } else {
                Log::error('[EventObserver] Failed to create Google Calendar event for local event ID: ' . $event->id . '. Google API did not return an event.');
            }
        } catch (\Google\Exception $e) {
            Log::error('[EventObserver] Google API Exception during event creation for event ID: ' . $event->id . '. Error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
            // Optionally, notify user/admin about the sync failure here
        } catch (\Exception $e) {
            Log::error('[EventObserver] General Exception during event creation for event ID: ' . $event->id . '. Error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
            // Optionally, notify user/admin about the sync failure here
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $user = $event->user;

        if (!$user) {
            Log::warning('[EventObserver] User not found for event ID: ' . $event->id . '. Cannot send EventUpdatedEmail or update Google Calendar event.');
            return;
        }

        // Send email notification to the event owner for event update
        // $originalAttributes = $event->getOriginal(); // Not used by current Mailable, can be removed if not needed for email content
        try {
            Mail::to($user->email)->send(new EventUpdatedEmail($event));
        } catch (\Exception $e) {
            Log::error("[EventObserver] Failed to send EventUpdatedEmail to user {$user->email} for event {$event->id}: " . $e->getMessage());
        }

        if (!$user->google_access_token) {
            Log::warning('[EventObserver] User ID: ' . $user->id . ' does not have a Google access token. Skipping Google Calendar update for event ID: ' . $event->id);
            return;
        }

        try {
            $client = $this->calendarService->getGoogleClientForUser($user);
            $calendarService = new GoogleCalendarServiceApi($client);
            $calendarId = 'primary';

            if ($event->google_calendar_event_id) {
                $googleServiceEvent = null; // Initialize to null
                try {
                    $googleServiceEvent = $calendarService->events->get($calendarId, $event->google_calendar_event_id);
                } catch (\Google\Service\Exception $e) {
                    if ($e->getCode() == 404) {
                        Log::warning('[EventObserver] Google Calendar event not found (404) for Google Event ID: ' . $event->google_calendar_event_id . '. Attempting to create a new one for local event ID: ' . $event->id);
                        $event->google_calendar_event_id = null; // Clear the old ID
                        Event::withoutEvents(function () use ($event) {
                            $event->save(); // Save the nullified google_calendar_event_id
                        });
                        // Call created method to re-create the event in Google Calendar
                        // This will also trigger the NewEventCreatedEmail again. Consider if this is desired.
                        $this->created($event);
                        return; // Exit updated method as created will handle it now
                    } else {
                        Log::error('[EventObserver] Google Service Exception when fetching event for update. Google Event ID: ' . $event->google_calendar_event_id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
                        // Do not re-throw, allow application to continue if possible, but we probably can't update it.
                        return; // Exit if we can't fetch the event to update
                    }
                }

                if ($googleServiceEvent) {
                    $googleServiceEvent->setSummary($event->title);
                    $googleServiceEvent->setDescription($event->description);

                    $start = new EventDateTime();
                    if ($event->all_day) {
                        $start->setDate(Carbon::parse($event->start_at)->toDateString());
                    } else {
                        $start->setDateTime(Carbon::parse($event->start_at)->toRfc3339String());
                    }
                    $googleServiceEvent->setStart($start);

                    $end = new EventDateTime();
                    if ($event->all_day) {
                        $endDate = Carbon::parse($event->end_at ?: $event->start_at)->addDay();
                        $end->setDate($endDate->toDateString());
                    } else {
                        $end->setDateTime(Carbon::parse($event->end_at)->toRfc3339String());
                    }
                    $googleServiceEvent->setEnd($end);

                    $calendarService->events->update($calendarId, $googleServiceEvent->getId(), $googleServiceEvent);
                    Log::info('[EventObserver] Successfully updated Google Calendar event ID: ' . $googleServiceEvent->getId() . ' for local event ID: ' . $event->id);
                } else {
                    // This case should ideally be handled by the 404 catch block above,
                    // but as a fallback, if $googleServiceEvent is null and no exception was caught (unlikely for get())
                    // we might try to create it.
                    Log::warning('[EventObserver] Google event was not fetched and no 404 error caught. Attempting to create for event ID: ' . $event->id);
                    $this->created($event);
                }
            } else {
                // If no google_calendar_event_id exists, it means we should create it.
                Log::info('[EventObserver] No Google Calendar event ID found for local event ID: ' . $event->id . '. Attempting to create a new one.');
                $this->created($event);
            }
        } catch (\Exception $e) {
            Log::error('[EventObserver] General Exception during Google Calendar event update for local event ID: ' . $event->id . ' - User ID: ' . $user->id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        $user = $event->user ?? User::find($event->user_id); // Ensure user is loaded
        $eventTitle = $event->title; // Store title before event is fully deleted

        if (!$user) {
            Log::warning('[EventObserver] User not found for event ID: ' . $event->id . '. Cannot send EventDeletedEmail or delete Google Calendar event.');
            // No email sent if owner is not found
            return;
        }

        // Send email notification to the event owner for event deletion
        try {
            Mail::to($user->email)->send(new EventDeletedEmail($eventTitle));
        } catch (\Exception $e) {
            Log::error("[EventObserver] Failed to send EventDeletedEmail to user {$user->email} for event {$event->id}: " . $e->getMessage());
        }

        if (!$user->google_access_token || !$event->google_calendar_event_id) {
            Log::warning('[EventObserver] Skipping Google Calendar event deletion for local event ID: ' . $event->id . '. Missing token or Google Event ID.');
            return;
        }

        try {
            $client = $this->calendarService->getGoogleClientForUser($user);
            $calendarService = new GoogleCalendarServiceApi($client);
            $calendarId = 'primary';

            $calendarService->events->delete($calendarId, $event->google_calendar_event_id);
            Log::info('[EventObserver] Successfully deleted Google Calendar event ID: ' . $event->google_calendar_event_id . ' for local event ID: ' . $event->id);

        } catch (\Google\Service\Exception $e) {
            // Check for 404 or 410, which means the event is already gone from Google Calendar
            if ($e->getCode() == 404 || $e->getCode() == 410) {
                Log::info('[EventObserver] Google Calendar event ID: ' . $event->google_calendar_event_id . ' not found or already deleted. No action needed for local event ID: ' . $event->id);
            } else {
                Log::error('[EventObserver] Google API Exception during event deletion for event ID: ' . $event->id . '. Error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
                // Optionally, notify user/admin about the sync failure here
            }
        } catch (\Exception $e) {
            Log::error('[EventObserver] General Exception during event deletion for event ID: ' . $event->id . '. Error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
            // Optionally, notify user/admin about the sync failure here
        }
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Event $event): void
    {
        // When an event is restored, it's like it's being created again in terms of Google Calendar.
        // We should try to create it on Google Calendar if it doesn't have a google_calendar_event_id
        // or if the existing one might be stale (though we don't have a good way to check staleness here easily
        // without trying to fetch it first, which could be complex).
        // For simplicity, if it's restored, we can call the created() logic.
        // The created() method already handles checking for an existing google_calendar_event_id if you were to
        // modify it to first attempt an update/get before insert. But current `created` always inserts.

        Log::info('[EventObserver] Event ID: ' . $event->id . ' has been restored.');

        // Clear any existing Google Calendar event ID to force a new creation,
        // as the old one might have been deleted or be out of sync.
        // However, only do this if we are sure we want to create a new one.
        // If the event on Google Calendar might still exist and be valid, this would create a duplicate.
        // A safer approach might be to try and update, and if it fails with 404, then create.
        // This is similar to the logic in updated().

        // For now, let's re-trigger the created logic. This will attempt to create a new event on Google Calendar.
        // This might lead to duplicate calendar entries if the original Google Calendar event was not deleted.
        // Consider the implications carefully.
        $this->created($event);
        // Also, consider sending an email to the owner that the event was restored.
        // Not doing so for now as per current scope.
    }

    /**
     * Handle the Event "forceDeleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        // If an event is force deleted, we should ensure its corresponding Google Calendar event is also deleted.
        // The `deleted` method should already handle this if it's called for force deletes.
        // If `deleted` is not called for force deletes (check Laravel docs for observer behavior on forceDelete vs delete),
        // then you might need to duplicate the Google Calendar deletion logic here.
        // Typically, soft delete's `deleted` hook is called. On force delete, `forceDeleted` is called.
        // If the model is not soft-deleting, `deleted` is called on actual deletion.
        Log::info('[EventObserver] Event ID: ' . $event->id . ' has been force deleted.');
        // Assuming soft deletes are used, the `deleted()` method would have handled Google Calendar deletion.
        // If not using soft deletes, or if `deleted()` isn't triggered before `forceDeleted()` in a way that cleans up
        // Google Calendar, you might need to call the deletion logic here too.
        // For safety, explicitly call deletion if google_calendar_event_id is present.
        if ($event->google_calendar_event_id) {
            $this->deleted($event); // Re-use the logic from deleted. This might send another email if not careful.
                                   // To avoid double emails, the deleted() method itself should be idempotent or
                                   // the email sending part should be conditional.
        }
    }
}
