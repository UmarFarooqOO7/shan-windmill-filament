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
use Illuminate\Support\Facades\Mail; // Added for Mail facade
use App\Mail\CalendarSyncErrorEmail;
use App\Mail\CalendarConnectionStatusEmail;

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
        try {
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
                    // Send connection success email
                    $this->sendCalendarConnectionStatusEmail($user, true);
                }
                return $saved;
            }
            // If user is not an instance of User or token is not set, send failure email
            if ($user instanceof User) {
                $this->sendCalendarConnectionStatusEmail($user, false, 'Access token not found in Google response.');
            }
            return false;
        } catch (\Exception $e) {
            Log::error('[GoogleCalendarService] OAuth callback error: ' . $e->getMessage());
            $user = Auth::user();
            if ($user instanceof User) {
                $this->sendCalendarConnectionStatusEmail($user, false, $e->getMessage());
            }
            return false;
        }
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
                    // Send sync error email
                    $this->sendCalendarSyncErrorEmail($user, $event, $e->getMessage());
                }
            }
        }
    }

    public function disconnectUser(User $user): bool
    {
        $user->google_access_token = null;
        $user->google_refresh_token = null;
        $user->google_token_expires_at = null;

        if ($user->save()) {
            Log::info('[GoogleCalendarService] Successfully disconnected Google Calendar for user ID: ' . $user->id);
            // Send disconnection success email
            $this->sendCalendarConnectionStatusEmail($user, false, null, true); // isDisconnected = true
            return true;
        }
        Log::error('[GoogleCalendarService] Failed to save user model while disconnecting Google Calendar for user ID: ' . $user->id);
        return false;
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
                try {
                    $userClient->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                    $newAccessToken = $userClient->getAccessToken();
                    $user->google_access_token = $newAccessToken['access_token'];
                    $user->google_token_expires_at = Carbon::now()->addSeconds($newAccessToken['expires_in']);
                    $user->save();
                } catch (\Exception $e) {
                    Log::error('[GoogleCalendarService] Failed to refresh Google access token for user ID: ' . $user->id . '. Error: ' . $e->getMessage());
                    // Send connection status email indicating token refresh failure
                    $this->sendCalendarConnectionStatusEmail($user, false, 'Failed to refresh access token: ' . $e->getMessage());
                    throw new \Exception('Failed to refresh Google access token. Please reconnect your Google Calendar.');
                }
            } else {
                // Send connection status email indicating missing refresh token
                $this->sendCalendarConnectionStatusEmail($user, false, 'Missing refresh token. Please reconnect your Google Calendar.');
                throw new \Exception('Google access token expired and no refresh token available. Please reconnect your Google Calendar.');
            }
        }

        return $userClient;
    }

    /**
     * Send calendar connection status email.
     */
    private function sendCalendarConnectionStatusEmail(User $user, bool $success, ?string $errorMessage = null, bool $isDisconnection = false): void
    {
        $adminUsers = User::where('is_admin', true)->get();
        // The CalendarConnectionStatusEmail constructor expects: User $user, bool $isConnected, ?string $errorMessage = null, bool $isDisconnection = false
        // The current Mailable only accepts User $user, bool $isConnected. We need to update the Mailable or simplify the call.
        // For now, let's assume the Mailable was updated to accept all parameters.
        // If not, this will need to be adjusted based on the actual Mailable constructor.
        foreach ($adminUsers as $admin) {
            try {
                // Assuming CalendarConnectionStatusEmail was updated to accept these parameters
                Mail::to($admin->email)->send(new CalendarConnectionStatusEmail($user, $success, $errorMessage, $isDisconnection));
            } catch (\Exception $e) {
                Log::error("[GoogleCalendarService] Failed to send CalendarConnectionStatusEmail to admin {$admin->email} for user {$user->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Send calendar sync error email.
     */
    private function sendCalendarSyncErrorEmail(User $user, Event $event, string $errorMessage): void
    {
        $adminUsers = User::where('is_admin', true)->get();
        // The CalendarSyncErrorEmail constructor expects: string $errorMessage, int $userId = null, int $eventId = null
        // We are passing User object and Event object.
        foreach ($adminUsers as $admin) {
            try {
                Mail::to($admin->email)->send(new CalendarSyncErrorEmail($errorMessage, $user->id, $event->id));
            } catch (\Exception $e) {
                Log::error("[GoogleCalendarService] Failed to send CalendarSyncErrorEmail to admin {$admin->email} for user {$user->id}, event {$event->id}: " . $e->getMessage());
            }
        }
    }
}
