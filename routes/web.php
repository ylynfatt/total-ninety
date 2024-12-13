<?php

use App\Http\Controllers\TeamsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('teams', TeamsController::class);
