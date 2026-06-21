<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

// Stateless check-in scan (station-token auth, per-station throttle).
Route::post('/scan', ScanController::class)
    ->middleware('throttle:scan')
    ->name('api.scan');

// Up-front PIN check for the scanner gate (tighter, IP-aware throttle to bound PIN guessing).
Route::post('/scan/verify', [ScanController::class, 'verifyPin'])
    ->middleware('throttle:scan-verify')
    ->name('api.scan.verify');
