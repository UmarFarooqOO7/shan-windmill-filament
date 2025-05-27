<?php

namespace App\Services;

use App\Models\User;
use Google_Client;
use Carbon\Carbon;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

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
            return $user->save();
        }
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
