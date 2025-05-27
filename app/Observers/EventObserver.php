<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Google\Service\Calendar as GoogleCalendarServiceApi;
use Google\Service\Calendar\Event as GoogleServiceCalendarEvent;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        $user = $event->user;

        if (!$user) {
            Log::warning('[EventObserver] User not found for event ID: ' . $event->id . '. Cannot create Google Calendar event.');
            return;
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
                $start->setDate(Carbon::parse($event->start_at)->toDateString());
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
            }
        } catch (\Exception $e) {
            Log::error('[EventObserver] Exception during Google Calendar event creation for local event ID: ' . $event->id . ' - User ID: ' . $user->id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        $user = $event->user;

        if (!$user) {
            Log::warning('[EventObserver] User not found for event ID: ' . $event->id . ' during update. Cannot update Google Calendar event.');
            return;
        }

        if (!$user->google_access_token) {
            // Log::warning('[EventObserver] User ID: ' . $user->id . ' does not have a Google access token. Skipping Google Calendar update for event ID: ' . $event->id);
            return;
        }

        try {
            $client = $this->calendarService->getGoogleClientForUser($user);
            $calendarService = new GoogleCalendarServiceApi($client);
            $calendarId = 'primary';

            if ($event->google_calendar_event_id) {
                try {
                    $googleServiceEvent = $calendarService->events->get($calendarId, $event->google_calendar_event_id);
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
                    }
                } catch (\Google\Service\Exception $e) {
                    if ($e->getCode() == 404) {
                        Log::warning('[EventObserver] Google Calendar event not found (404) for Google Event ID: ' . $event->google_calendar_event_id . '. Attempting to create a new one for local event ID: ' . $event->id);
                        $event->google_calendar_event_id = null; // Clear the old ID
                        // Detach observer temporarily to prevent infinite loop if save triggers update again
                        Event::withoutEvents(function () use ($event) {
                            $event->save(); // Save the nullified google_calendar_event_id
                        });
                        $this->created($event); // Re-trigger the created logic
                    } else {
                        Log::error('[EventObserver] Google Service Exception during Google Calendar event update for local event ID: ' . $event->id . ' - Google Event ID: ' . $event->google_calendar_event_id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
                        // Do not re-throw, allow application to continue if possible
                    }
                }
            } else {
                // If no google_calendar_event_id exists, it means we should create it.
                $this->created($event);
            }
        } catch (\Exception $e) {
            Log::error('[EventObserver] Exception during Google Calendar event update for local event ID: ' . $event->id . ' - User ID: ' . $user->id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
        }
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        $user = $event->user;

        if (!$user) {
            // Log::warning('[EventObserver] User not found for event ID: ' . $event->id . ' during delete. Cannot delete Google Calendar event.');
            // If user is null, it might be a cleanup process or an event not tied to a specific user's calendar.
            return;
        }

        if (!$user->google_access_token || !$event->google_calendar_event_id) {
            // Log::warning('[EventObserver] Skipping Google Calendar event deletion for local event ID: ' . $event->id . '. Missing token or Google Event ID.');
            return;
        }

        try {
            $client = $this->calendarService->getGoogleClientForUser($user);
            $calendarService = new GoogleCalendarServiceApi($client);
            $calendarId = 'primary';

            $calendarService->events->delete($calendarId, $event->google_calendar_event_id);

        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() == 404) {
                Log::warning('[EventObserver] Google Calendar event not found (404) during deletion. Google Event ID: ' . $event->google_calendar_event_id . '. Ignoring.');
            } else {
                Log::error('[EventObserver] Google Service Exception during Google Calendar event deletion for Google Event ID: ' . $event->google_calendar_event_id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
            }
        } catch (\Exception $e) {
            Log::error('[EventObserver] Exception during Google Calendar event deletion for Google Event ID: ' . $event->google_calendar_event_id . ' - User ID: ' . $user->id . ' - Message: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
        }
    }
}
