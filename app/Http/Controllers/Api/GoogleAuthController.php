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
use App\Models\{User,Event};


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

        return $this->success(['url' => $authUrl]);
    }

    public function callback(Request $request)
    {
        $client = $this->google->getClient();

        if (!$request->has('code')) {
            return $this->error( 'Google authentication failed', 400);
        }

        $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));

        if (isset($token['error'])) {
            return $this->error( 'Invalid authorization code', 400);
        }

        // Save tokens for authenticated API user
        // $user = auth('sanctum')->user(); // or auth()->user() if already authenticated
        // if (!$user) {
        //     return $this->error( 'User not authenticated'], 401);
        // }

        $user = User::where('email', 'mesumbhatti111@gmail.com')->first();

        if (!$user) {
            return $this->error( 'User not found', 404);
        }

        $user->google_access_token = json_encode($token);
        $user->google_refresh_token = $token['refresh_token'] ?? $user->google_refresh_token;
        $user->google_token_expires_at = now()->addSeconds($token['expires_in']);
        $user->is_google_connected = true;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Connected with Google!']);
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

    public function viewCalendar()
    {
        // Get the user (hardcoded for now — replace later with auth()->user())
        $user = auth()->user();
        $events = Event::where('user_id',$user->id)->get();

        // if (!$user || !$user->google_access_token) {
        //     return $this->error( 'Not connected to Google Calendar', 401);
        // }

        // $token = json_decode($user->google_access_token, true);

        // $service = $this->google->getService($token);
        // $events = $service->events->listEvents('primary');

        // $calendarEvents = [];
        // foreach ($events->getItems() as $event) {
        //      $calendarEvents[] = [
        //         'title' => $event->getSummary(),
        //         'description' => $event->getDescription(),
        //         'start' => $event->start->dateTime ?: $event->start->date,
        //         'end'   => $event->end->dateTime ?: $event->end->date,
        //         'id'    => $event->id,
        //     ];
        // }

        return $this->success($events);
    }

    public function createEvent(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'all_day'     => 'nullable|boolean',

        ]);

        // Find the user by email
        $user = auth()->user();
    
        // 1️⃣ Create local event first
        $event = Event::create([
            'title'       => $request->name,
            'description' => $request->description,
            'start_at'    => $request->start_date,
            'end_at'      => $request->end_date,
            'all_day'     => $request->all_day ?? false,
            'user_id'     => $user->id,
            'google_calendar_event_id' => null,
        ]);

        // 2️⃣ If Google account not connected, return local event
        if (!$user->google_access_token) {
            return response()->json([
                'message' => 'Local event created but Google account not connected',
                'local_event' => $event
            ], 201);
        }

        try {
            // Setup Google Client
            $client = new Google_Client();
            $client->setApplicationName('Laravel Google Calendar');
            $client->setScopes(Google_Service_Calendar::CALENDAR);
            $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
            $client->setAccessType('offline');

            // Restore access token
            $accessToken = json_decode($user->google_access_token, true);
            $client->setAccessToken($accessToken);

            // Refresh if expired
            if ($client->isAccessTokenExpired()) {
                if ($user->google_refresh_token) {
                    $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                    $user->google_access_token = json_encode($client->getAccessToken());
                    $user->save();
                } else {
                    return response()->json([
                        'message' => 'Local event created but Google token expired and no refresh token found',
                        'local_event' => $event
                    ], 201);
                }
            }

            $service = new Google_Service_Calendar($client);

            // Create Google Calendar event
            $googleEvent = new Google_Service_Calendar_Event([
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
            $createdEvent = $service->events->insert($calendarId, $googleEvent);

            // 3️⃣ Update local event with Google Event ID
            $event->update([
                'google_calendar_event_id' => $createdEvent->id
            ]);

            return response()->json([
                'message' => 'Event created successfully',
                'local_event' => $event,
                'google_event_id' => $createdEvent->id,
                'htmlLink' => $createdEvent->htmlLink
            ], 201);

        } catch (\Exception $e) {
            \Log::error("Google Event Creation Failed: " . $e->getMessage());
            return response()->json([
                'message' => 'Local event created but failed to create Google Calendar event',
                'local_event' => $event
            ], 201);
        }
    }

    public function updateEvent(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'all_day'     => 'nullable|boolean',
        ]);

        // Find the user by email
        $user = auth()->user();

        // Find local event
        $event = Event::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // 1️⃣ Update local DB first
        $event->update([
            'title'       => $request->name,
            'description' => $request->description,
            'start_at'    => $request->start_date,
            'end_at'      => $request->end_date,
            'all_day'     => $request->all_day ?? false,
        ]);

        // 2️⃣ If Google account not connected, return local update
        if (!$user->google_access_token) {
            return response()->json([
                'message' => 'Local event updated but Google account not connected',
                'local_event' => $event
            ], 200);
        }

        try {
            // Setup Google Client
            $client = new \Google_Client();
            $client->setApplicationName('Laravel Google Calendar');
            $client->setScopes(\Google_Service_Calendar::CALENDAR);
            $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
            $client->setAccessType('offline');

            // Restore access token
            $accessToken = json_decode($user->google_access_token, true);
            $client->setAccessToken($accessToken);

            // Refresh token if expired
            if ($client->isAccessTokenExpired()) {
                if ($user->google_refresh_token) {
                    $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                    $user->google_access_token = json_encode($client->getAccessToken());
                    $user->save();
                } else {
                    return response()->json([
                        'message' => 'Local event updated but Google token expired and no refresh token found',
                        'local_event' => $event
                    ], 200);
                }
            }

            $service = new \Google_Service_Calendar($client);

            // If Google event exists, update it; otherwise create it
            if ($event->google_calendar_event_id) {
                $googleEvent = $service->events->get('primary', $event->google_calendar_event_id);
            } else {
                $googleEvent = new \Google_Service_Calendar_Event();
            }

            $googleEvent->setSummary($request->name);
            $googleEvent->setDescription($request->description ?? '');
            $googleEvent->setStart(new \Google_Service_Calendar_EventDateTime([
                'dateTime' => \Carbon\Carbon::parse($request->start_date)->toRfc3339String(),
                'timeZone' => 'Asia/Karachi',
            ]));
            $googleEvent->setEnd(new \Google_Service_Calendar_EventDateTime([
                'dateTime' => \Carbon\Carbon::parse($request->end_date)->toRfc3339String(),
                'timeZone' => 'Asia/Karachi',
            ]));

            // Save to Google Calendar
            if ($event->google_calendar_event_id) {
                $updatedGoogleEvent = $service->events->update('primary', $event->google_calendar_event_id, $googleEvent);
            } else {
                $updatedGoogleEvent = $service->events->insert('primary', $googleEvent);
                $event->update(['google_calendar_event_id' => $updatedGoogleEvent->id]);
            }

            return response()->json([
                'message' => 'Event updated successfully',
                'local_event' => $event,
                'google_event_id' => $event->google_calendar_event_id,
                'htmlLink' => $updatedGoogleEvent->htmlLink ?? null
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Google Event Update Failed: " . $e->getMessage());
            return response()->json([
                'message' => 'Local event updated but failed to update Google Calendar',
                'local_event' => $event
            ], 200);
        }
    }

    public function deleteGoogleEvent(Request $request)
    {
        $request->validate([
            'event_id' => 'required|integer', // your app's event ID
        ]);

        $user = auth()->user();

        if (!$user || !$user->is_google_connected || !$user->google_access_token) {
            return $this->error('User not connected to Google', 401);
        }

        // 1️⃣ Find the event in your DB
        $event = Event::where('id', $request->event_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // 2️⃣ Store google_event_id before deleting DB record
        $googleEventId = $event->google_event_id;

        // 3️⃣ Delete from DB first
        $event->delete();

        // 4️⃣ If google_event_id exists, delete from Google
        if ($googleEventId) {
            $token = json_decode($user->google_access_token, true);
            $service = $this->google->getService($token);

            try {
                $service->events->delete('primary', $googleEventId);
            } catch (\Exception $e) {
                \Log::warning("Google Event Deletion Failed: " . $e->getMessage());
                return $this->error('Deleted from DB but failed to delete from Google Calendar', 500);
            }
        }

        return $this->success(null, 'Event deleted from both DB and Google Calendar');
    }
}
