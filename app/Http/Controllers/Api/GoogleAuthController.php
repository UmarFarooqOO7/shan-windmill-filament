<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Session;
use App\Services\GoogleCalendorServiceApi;
use App\Models\User;


class GoogleAuthController extends Controller
{
     protected $google;

    public function __construct(GoogleCalendorServiceApi $google)
    {
        $this->google = $google;
    }

    public function redirect()
    {
        $client = $this->google->getClient();
        $authUrl = $client->createAuthUrl();

        return response()->json(['url' => $authUrl]);
    }

    public function callback(Request $request)
    {
        $client = $this->google->getClient();

        if (!$request->has('code')) {
            return response()->json(['error' => 'Google authentication failed'], 400);
        }

        $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));

        if (isset($token['error'])) {
            return response()->json(['error' => 'Invalid authorization code'], 400);
        }

        // Save tokens for authenticated API user
        // $user = auth('sanctum')->user(); // or auth()->user() if already authenticated
        // if (!$user) {
        //     return response()->json(['error' => 'User not authenticated'], 401);
        // }

        $user = User::where('email', 'mesumbhatti111@gmail.com')->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->google_access_token = json_encode($token);
        $user->google_refresh_token = $token['refresh_token'] ?? $user->google_refresh_token;
        $user->google_token_expires_at = now()->addSeconds($token['expires_in']);
        $user->is_google_connected = true;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Connected with Google!']);
    }


    public function viewCalendar()
    {
        // Get the user (hardcoded for now — replace later with auth()->user())
        $user = User::where('email', 'mesumbhatti111@gmail.com')->first();

        if (!$user || !$user->google_access_token) {
            return response()->json(['error' => 'Not connected to Google Calendar'], 401);
        }

        $token = json_decode($user->google_access_token, true);

        $service = $this->google->getService($token);
        $events = $service->events->listEvents('primary');

        $calendarEvents = [];
        foreach ($events->getItems() as $event) {
            $calendarEvents[] = [
                'title' => $event->getSummary(),
                'description' => $event->getDescription(),
                'start' => $event->start->dateTime ?: $event->start->date,
                'end'   => $event->end->dateTime ?: $event->end->date,
                'id'    => $event->id,
            ];
        }

        return response()->json($calendarEvents);
    }

    public function createEvent(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'email' => 'required|email'
        ]);

        // Get user by email (direct, no Sanctum needed)
        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->google_access_token) {
            return response()->json(['error' => 'Google account not connected'], 401);
        }

        // Setup Google Client
        $client = new Google_Client();
        $client->setApplicationName('Laravel Google Calendar');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAuthConfig(storage_path('app/google-calendar/credentials.json')); // Path to credentials
        $client->setAccessType('offline');

        // Restore Access Token
        $accessToken = json_decode($user->google_access_token, true);
        $client->setAccessToken($accessToken);

        // Refresh token if expired
        if ($client->isAccessTokenExpired()) {
            if ($user->google_refresh_token) {
                $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $user->google_access_token = json_encode($client->getAccessToken());
                $user->save();
            } else {
                return response()->json(['error' => 'Google token expired and no refresh token found'], 401);
            }
        }

        $service = new Google_Service_Calendar($client);

        // Create Event
        $event = new Google_Service_Calendar_Event([
            'summary'     => $request->name,
            'description' => $request->description,
            'start'       => [
                'dateTime' => Carbon::parse($request->start_date)->toRfc3339String(),
                'timeZone' => 'Asia/Karachi',
            ],
            'end'         => [
                'dateTime' => Carbon::parse($request->end_date)->toRfc3339String(),
                'timeZone' => 'Asia/Karachi',
            ],
        ]);

        $calendarId = 'primary';
        $createdEvent = $service->events->insert($calendarId, $event);

        return response()->json([
            'message' => 'Event created successfully',
            'event_id' => $createdEvent->id,
            'htmlLink' => $createdEvent->htmlLink
        ], 201);
    }


    public function disconnect()
    {
        $user = auth()->user();
        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'is_google_connected' => false,
        ]);

        return response()->json(['success' => true, 'message' => 'Disconnected from Google Calendar']);
    }

    public function createGoogleEvent(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start'       => 'required|date',
            'end'         => 'required|date|after_or_equal:start',
        ]);

        $user = auth()->user();
        if (!$user || !$user->is_google_connected || !$user->google_access_token) {
            return response()->json(['error' => 'Google Calendar not connected'], 401);
        }

        $token = json_decode($user->google_access_token, true);

        if ($user->google_token_expires_at->isPast() && $user->google_refresh_token) {
            $client = $this->google->getClient();
            $client->setAccessToken($token);
            $client->refreshToken($user->google_refresh_token);

            $newToken = $client->getAccessToken();
            $user->google_access_token = json_encode($newToken);
            $user->google_token_expires_at = now()->addSeconds($newToken['expires_in']);
            $user->save();
            $token = $newToken;
        }

        $service = $this->google->getService($token);

        $googleEvent = new \Google_Service_Calendar_Event([
            'summary'     => $request->title,
            'description' => $request->description,
            'start' => ['dateTime' => Carbon::parse($request->start)->toRfc3339String()],
            'end'   => ['dateTime' => Carbon::parse($request->end)->toRfc3339String()],
        ]);

        $inserted = $service->events->insert('primary', $googleEvent);

        return response()->json(['success' => true, 'event_id' => $inserted->id]);
    }

    public function updateGoogleEvent(Request $request, $googleEventId)
    {
        $user = auth()->user();

        if (!$user || !$user->is_google_connected || !$user->google_access_token) {
            return response()->json(['error' => 'User not authenticated or Google not connected'], 401);
        }

        $validated = $request->validate([
            'name'     => 'required|string', // Event title
            'description' => 'nullable|string',
            'start'       => 'required|date',
            'end'         => 'required|date|after:start',
        ]);

        $token = json_decode($user->google_access_token, true);
        $service = $this->google->getService($token);

        try {
            // Fetch the existing event from Google Calendar
            $event = $service->events->get('primary', $googleEventId);

            // Update fields
            $event->setSummary($validated['name']);
            $event->setDescription($validated['description'] ?? '');

            $event->setStart(new \Google\Service\Calendar\EventDateTime([
                'dateTime' => date('c', strtotime($validated['start'])),
                'timeZone' => config('app.timezone'),
            ]));

            $event->setEnd(new \Google\Service\Calendar\EventDateTime([
                'dateTime' => date('c', strtotime($validated['end'])),
                'timeZone' => config('app.timezone'),
            ]));

            // Update on Google Calendar
            $updatedEvent = $service->events->update('primary', $googleEventId, $event);

            return response()->json([
                'message' => 'Google Calendar event updated successfully',
                'event'   => $updatedEvent,
            ]);
        } catch (\Exception $e) {
            \Log::error("Google Event Update Failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to update Google Calendar event'], 500);
        }
    }

    public function deleteGoogleEvent(Request $request)
    {
        $request->validate([
            'google_event_id' => 'required|string',
        ]);

        $user = auth()->user();

        if (!$user || !$user->is_google_connected || !$user->google_access_token) {
            return response()->json(['error' => 'User not connected to Google'], 401);
        }

        $token = json_decode($user->google_access_token, true);

        $service = $this->google->getService($token);

        try {
            $service->events->delete('primary', $request->google_event_id);
            return response()->json(['message' => 'Event deleted from Google Calendar']);
        } catch (\Exception $e) {
            \Log::warning("Google Event Deletion Failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete from Google Calendar'], 500);
        }
    }
}
