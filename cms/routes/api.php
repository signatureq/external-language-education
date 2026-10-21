<?php

use App\Http\Controllers\ApplicationController;
use Illuminate\Support\Facades\Route;

Route::get('/applications/token', [ApplicationController::class, 'token'])->middleware('throttle:application-token');
Route::post('/applications', [ApplicationController::class, 'store'])->middleware('throttle:application-submit');
