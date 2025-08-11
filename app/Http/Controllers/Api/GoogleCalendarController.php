<?php

namespace App\Http\Controllers\Api;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Google\Service\Calendar\Event as GoogleEvent;
use Carbon\Carbon;
use App\Http\Controllers\Controller;


class GoogleCalendarController extends Controller
{
    protected $google;

    public function __construct(GoogleCalendarService $google)
    {
        $this->google = $google;
    }

    // Step 1: Redirect user to Google login
    public function connect()
    {
        return response()->json([
            'auth_url' => $this->google->getAuthUrl()
        ]);
    }

    // Step 2: Handle Google OAuth callback
    public function callback(Request $request)
    {
        if ($this->google->handleOAuthCallback($request)) {
            return response()->json(['message' => 'Google Calendar connected successfully.']);
        }
        return response()->json(['message' => 'Failed to connect Google Calendar.'], 400);
    }

    // List events from user's Google Calendar
    public function listEvents()
    {
        $client = $this->google->getGoogleClientForUser(auth()->user());
        $service = new \Google\Service\Calendar($client);

        $events = $service->events->listEvents('primary', [
            'timeMin' => Carbon::now()->toRfc3339String(),
            'maxResults' => 20,
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ]);

        return response()->json($events->getItems());
    }

    // Create a new event
    public function createEvent(Request $request)
    {
        $client = $this->google->getGoogleClientForUser(auth()->user());
        $service = new \Google\Service\Calendar($client);

        $event = new GoogleEvent([
            'summary' => $request->summary,
            'description' => $request->description,
            'start' => ['dateTime' => Carbon::parse($request->start)->toRfc3339String(), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => Carbon::parse($request->end)->toRfc3339String(), 'timeZone' => 'UTC'],
        ]);

        $createdEvent = $service->events->insert('primary', $event);
        return response()->json($createdEvent);
    }

    // Update event
    public function updateEvent(Request $request, $eventId)
    {
        $client = $this->google->getGoogleClientForUser(auth()->user());
        $service = new \Google\Service\Calendar($client);

        $event = $service->events->get('primary', $eventId);
        $event->setSummary($request->summary ?? $event->getSummary());
        $event->setDescription($request->description ?? $event->getDescription());

        $updatedEvent = $service->events->update('primary', $eventId, $event);
        return response()->json($updatedEvent);
    }

    // Delete event
    public function deleteEvent($eventId)
    {
        $client = $this->google->getGoogleClientForUser(auth()->user());
        $service = new \Google\Service\Calendar($client);

        $service->events->delete('primary', $eventId);
        return response()->json(['message' => 'Event deleted successfully.']);
    }

    // Disconnect user from Google Calendar
    public function disconnect()
    {
        $this->google->disconnectUser(auth()->user());
        return response()->json(['message' => 'Disconnected from Google Calendar.']);
    }
}
