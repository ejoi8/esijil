<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupCertificateRequest;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Certificates\StoredCertificatePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateLookupController extends Controller
{
    public function index(): View
    {
        return view('certificate-lookup.index');
    }

    public function search(LookupCertificateRequest $request): RedirectResponse
    {
        $email = (string) $request->validated('email');

        $participant = Participant::query()
            ->where('email', $email)
            ->first();

        if ($participant === null) {
            Log::info('certificate.lookup_miss', [
                'email_hash' => hash('sha256', mb_strtolower($email)),
                'ip' => $request->ip(),
            ]);

            $request->session()->forget('certificate_lookup_participant_id');

            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'No certificate record was found for that email.',
                ]);
        }

        $request->session()->put('certificate_lookup_participant_id', $participant->id);

        return redirect()->route('certificate-lookup.result');
    }

    public function result(): View|RedirectResponse
    {
        $participantId = session('certificate_lookup_participant_id');

        if ($participantId === null) {
            return redirect()->route('certificate-lookup.index');
        }

        $participant = Participant::query()
            ->with([
                'registrations' => fn ($query) => $query
                    ->with(['event'])
                    ->orderByDesc('registered_at'),
            ])
            ->find($participantId);

        if ($participant === null) {
            session()->forget('certificate_lookup_participant_id');

            return redirect()->route('certificate-lookup.index');
        }

        return view('certificate-lookup.result', [
            'participant' => $participant,
            'registrations' => $participant->registrations,
        ]);
    }

    /**
     * Public verification of a single certificate by its serial number (the QR
     * on the certificate links here). Shows whether the serial maps to a genuine,
     * issued certificate without requiring the holder's No. KP.
     */
    public function verify(string $serial): View
    {
        $registration = Registration::query()
            ->whereNotNull('certificate_template_id')
            ->where('cert_serial_number', $serial)
            ->with(['event', 'participant'])
            ->first();

        return view('certificate-lookup.verify', [
            'serial' => $serial,
            'registration' => $registration,
        ]);
    }

    public function download(Registration $registration, StoredCertificatePdf $storedCertificatePdf): StreamedResponse
    {
        $registration->loadMissing('certificateTemplate', 'event.certificateTemplate', 'participant');

        if (session('certificate_lookup_participant_id') !== $registration->participant_id) {
            Log::warning('certificate.lookup_forbidden', [
                'registration_id' => $registration->id,
                'ip' => request()->ip(),
            ]);

            abort(403);
        }

        // Lookup intentionally ignores event status — certificates remain
        // downloadable after an event ends. A registration with no certificate
        // template has nothing to render, so 404 (matches the event registration
        // and admin download endpoints).
        abort_unless($registration->certificate_template_id !== null, 404);

        return $storedCertificatePdf->download($registration);
    }
}
