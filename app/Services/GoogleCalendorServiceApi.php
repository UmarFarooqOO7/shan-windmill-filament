<?php
// app/Services/GoogleCalendarService.php
namespace App\Services;

use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class GoogleCalendorServiceApi
{
    public function getClient()
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri(route('google.callbackapi'));
        $client->setScopes([
            Google_Service_Calendar::CALENDAR,
        ]);
        return $client;
    }

    public function getService($accessToken)
    {
        $client = $this->getClient();
        $client->setAccessToken($accessToken);
        return new Google_Service_Calendar($client);
    }
}
