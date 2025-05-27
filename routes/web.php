<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\GoogleCalendarService; // Add this line
use Illuminate\Http\Request; // Add this line

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
Route::get('/google-auth', function (GoogleCalendarService $calendarService) { // Inject service
    $authUrl = $calendarService->getAuthUrl();
    return redirect($authUrl);
})->name('google-auth'); // Added name for route

Route::get('/oauth2callback', function (Request $request, GoogleCalendarService $calendarService) { // Inject service and request
    if ($calendarService->handleOAuthCallback($request)) {
        $user = Auth::user();
        return 'Token saved for user: ' . $user->email; // User is already fetched in service
    } else {
        return 'Failed to save token or no authenticated user.';
    }
})->name('oauth2callback'); // Added name for route

