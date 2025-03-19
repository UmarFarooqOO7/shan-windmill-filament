<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login'); // Redirect to Filament's login page
})->name('login');
