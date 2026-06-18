<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

// Stateless check-in scan (station-token auth, per-station throttle).
Route::post('/scan', ScanController::class)
    ->middleware('throttle:scan')
    ->name('api.scan');
