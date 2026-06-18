<?php

use App\Http\Controllers\AdminRegistrationCertificateDownloadController;
use App\Http\Controllers\CertificateLookupController;
use App\Http\Controllers\CustomFieldFileController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\ParticipantStatusController;
use App\Http\Controllers\ScannerController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/semakan', [CertificateLookupController::class, 'index'])->name('certificate-lookup.index');
Route::post('/semakan', [CertificateLookupController::class, 'search'])
    ->middleware('throttle:certificate-lookup')
    ->name('certificate-lookup.search');
Route::get('/semakan/keputusan', [CertificateLookupController::class, 'result'])->name('certificate-lookup.result');
Route::get('/semakan/sijil/{serial}', [CertificateLookupController::class, 'verify'])
    ->middleware('throttle:certificate-lookup')
    ->name('certificate-lookup.verify');
Route::get('/certificates/{registration}/download', [CertificateLookupController::class, 'download'])
    ->middleware('throttle:certificate-download')
    ->name('certificate-lookup.download');

// Public scanner page — the station token in the URL is the operator's bearer auth.
Route::get('/scan/{stationToken}', [ScannerController::class, 'show'])->name('scan.show');

// Participant attendance pass — their public_token check-in QR + event status.
Route::get('/r/{publicToken}', [ParticipantStatusController::class, 'show'])->name('participant.status');

Route::middleware('signed')->group(function (): void {
    Route::get('/events/{event:public_id}/register', [EventRegistrationController::class, 'show'])->name('events.register.show');
    Route::post('/events/{event:public_id}/register', [EventRegistrationController::class, 'store'])
        ->middleware('throttle:event-registration')
        ->name('events.register.store');
});

Route::get('/registrations/{registration}/success', [EventRegistrationController::class, 'success'])->name('events.register.success');
Route::get('/registrations/{registration}/certificate', [EventRegistrationController::class, 'downloadCertificate'])->name('events.register.certificate');
Route::middleware('auth')->group(function (): void {
    Route::get('/auth/registrations/{registration}/certificate', AdminRegistrationCertificateDownloadController::class)
        ->name('auth.registrations.certificate');
    Route::get('/auth/files/{entity}/{record}/{key}', CustomFieldFileController::class)
        ->name('auth.custom-field-file');
});

if (app()->environment('local')) {

    Route::get('/dev/{identifier}', function ($identifier) {

        $user = User::query()
            ->when(is_numeric($identifier), fn ($q) => $q->where('id', $identifier))
            ->when(filter_var($identifier, FILTER_VALIDATE_EMAIL), fn ($q) => $q->orWhere('email', $identifier))
            ->first();

        abort_if(! $user, 404, 'User not found');

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        Auth::login($user);
        session()->regenerate();

        return redirect('/auth');
    });

    Route::get('/dev-logout', function () {
        Auth::logout();

        return redirect('/auth');
    });
}
