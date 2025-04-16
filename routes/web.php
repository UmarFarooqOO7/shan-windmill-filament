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
