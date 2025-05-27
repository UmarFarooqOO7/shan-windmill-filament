<?php

namespace App\Services;

use App\Models\User;
use Google_Client;
use Carbon\Carbon;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Event; // Added for fetching events
use App\Observers\EventObserver; // Added for syncing events
use Illuminate\Support\Facades\Log; // Added for logging

class GoogleCalendarService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
        $this->client->setAccessType('offline');
        $this->client->setRedirectUri(route('oauth2callback'));
        $this->client->setScopes([Calendar::CALENDAR]);
    }

    public function getAuthUrl(): string
    {
        $this->client->setPrompt('consent');
        return $this->client->createAuthUrl();
    }

    public function handleOAuthCallback(Request $request): bool
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($request->get('code'));

        $user = Auth::user();
        if ($user instanceof User && isset($token['access_token'])) {
            $user->google_access_token = $token['access_token'];
            if (isset($token['refresh_token'])) {
                $user->google_refresh_token = $token['refresh_token'];
            }
            $user->google_token_expires_at = Carbon::now()->addSeconds($token['expires_in']);
            $saved = $user->save();
            if ($saved) {
                // After successfully saving the token, sync existing events
                $this->syncUserEventsToGoogle($user);
            }
            return $saved;
        }
        return false;
    }

    public function syncUserEventsToGoogle(User $user): void
    {
        if (!$user->google_access_token) {
            Log::warning('[GoogleCalendarService] Cannot sync events: User ID ' . $user->id . ' does not have a Google access token.');
            return;
        }

        $eventsToSync = Event::where('user_id', $user->id)
                             ->whereNull('google_calendar_event_id')
                             ->get();

        if ($eventsToSync->isNotEmpty()) {
            $eventObserver = app(EventObserver::class);
            foreach ($eventsToSync as $event) {
                try {
                    // Ensure the event has a user relationship loaded if observer relies on it directly
                    // $event->loadMissing('user'); // This might not be necessary if observer re-fetches or uses passed user
                    $eventObserver->created($event); // This will attempt to create the event on Google Calendar
                } catch (\Exception $e) {
                    Log::error('[GoogleCalendarService] Failed to sync event ID: ' . $event->id . ' to Google Calendar for user ID: ' . $user->id . '. Error: ' . $e->getMessage(), ['exception_trace' => $e->getTraceAsString()]);
                }
            }
        }
    }

    public function getGoogleClientForUser(User $user): Google_Client
    {
        if (!$user->google_access_token) {
            throw new \Exception('User has not authenticated with Google Calendar.');
        }

        $userClient = new Google_Client();
        $userClient->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
        $userClient->setAccessType('offline');

        $userClient->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $user->google_token_expires_at ? $user->google_token_expires_at->getTimestamp() - time() : 0,
        ]);

        if ($userClient->isAccessTokenExpired()) {
            if ($user->google_refresh_token) {
                $userClient->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                $newAccessToken = $userClient->getAccessToken();

                $user->google_access_token = $newAccessToken['access_token'];
                $user->google_token_expires_at = Carbon::now()->addSeconds($newAccessToken['expires_in']);
                if (isset($newAccessToken['refresh_token'])) {
                    $user->google_refresh_token = $newAccessToken['refresh_token'];
                }
                $user->save();
                $userClient->setAccessToken($newAccessToken);
            } else {
                throw new \Exception('Google Calendar access token expired and no refresh token available. Please re-authenticate.');
            }
        }
        return $userClient;
    }
}
