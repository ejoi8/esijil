<?php

use App\Http\Controllers\AdminRegistrationCertificateDownloadController;
use App\Http\Controllers\CertificateLookupController;
use App\Http\Controllers\EventRegistrationController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/semakan', [CertificateLookupController::class, 'index'])->name('certificate-lookup.index');
Route::post('/semakan', [CertificateLookupController::class, 'search'])
    ->middleware('throttle:certificate-lookup')
    ->name('certificate-lookup.search');
Route::get('/semakan/keputusan', [CertificateLookupController::class, 'result'])->name('certificate-lookup.result');
Route::get('/certificates/{registration}/download', [CertificateLookupController::class, 'download'])
    ->middleware('throttle:certificate-download')
    ->name('certificate-lookup.download');

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
