<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Keep for logging potential errors

Route::get('/', function () {
    return redirect()->to('/admin/calendar');
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


Route::get('/google-auth', function (GoogleCalendarService $calendarService) {
    $authUrl = $calendarService->getAuthUrl();
    return redirect($authUrl);
})->name('google-auth');

Route::get('/oauth2callback', function (Request $request, GoogleCalendarService $googleCalendarService) {
    if ($googleCalendarService->handleOAuthCallback($request)) {
        // The sync logic is now inside handleOAuthCallback in the service
        // Redirect to the main calendar page
        return redirect()->to('/admin/calendar')->with('status', 'Successfully connected to Google Calendar and initiated event sync!');
    } else {
        // Token save failed or user denied access, or no authenticated user after callback
        $user = Auth::user();
        if (!$user) {
            Log::error('OAuth callback failed or no authenticated user found after attempting token save.');
            return redirect()->to('/admin/login')->with('error', 'Authentication session issue after Google OAuth. Please try logging in again.');
        }
        return redirect()->to('/admin/calendar')->with('error', 'Failed to connect to Google Calendar. Please try again.');
    }
})->name('oauth2callback');

Route::get('/google-disconnect', function (Request $request, GoogleCalendarService $googleCalendarService) {
    $user = Auth::user();
    if ($user && $googleCalendarService->disconnectUser($user)) {
        return redirect()->to('/admin/calendar')->with('status', 'Successfully disconnected from Google Calendar.');
    }
    return redirect()->to('/admin/calendar')->with('error', 'Failed to disconnect from Google Calendar.');
})->name('google-disconnect')->middleware('auth'); // Ensure only authenticated users can access

