<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route for printing leads
Route::get('/print-leads', function () {
    // Get the record IDs and columns from the session
    $recordIds = session('print_record_ids', []);
    $columns = session('print_columns', []);

    // Fetch the actual records from the database using the IDs
    $records = \App\Models\Lead::whereIn('id', $recordIds)->get();

    return view('print-leads', [
        'records' => $records,
        'columns' => $columns
    ]);
})->name('print-leads');

// routes/web.php
Route::get('/google-auth', function () {
    $client = new \Google_Client();
    $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
    $client->setScopes([\Google_Service_Calendar::CALENDAR]);
    $client->setAccessType('offline');
    $client->setPrompt('consent');
    $client->setRedirectUri('http://127.0.0.1:8000/oauth2callback'); // Adjust this to your redirect URI

    $authUrl = $client->createAuthUrl();
    return redirect($authUrl);
});

use Illuminate\Support\Facades\Storage;

Route::get('/oauth2callback', function (Illuminate\Http\Request $request) {
    $client = new \Google_Client();
    $client->setAuthConfig(storage_path('app/google-calendar/credentials.json'));
    $client->setRedirectUri('http://127.0.0.1:8000/oauth2callback');
    $client->setScopes([\Google_Service_Calendar::CALENDAR]);
    $client->setAccessType('offline');

    $token = $client->fetchAccessTokenWithAuthCode($request->get('code'));

    // Save the token for future use
    Storage::put('google-calendar/token.json', json_encode($token));

    return 'Token saved!';
});

