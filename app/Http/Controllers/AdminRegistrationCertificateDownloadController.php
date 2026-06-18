<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\Certificates\StoredCertificatePdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRegistrationCertificateDownloadController extends Controller
{
    public function __invoke(Request $request, Registration $registration, StoredCertificatePdf $storedCertificatePdf): StreamedResponse
    {
        // Authorize: only panel users who may view registrations. Without this,
        // any authenticated web user could enumerate certificates (PII) by id.
        abort_unless($request->user()?->can('registration.view') ?? false, 403);

        $registration->load('certificateTemplate', 'event.certificateTemplate', 'participant');

        abort_unless($registration->certificate_template_id !== null, 404);

        return $storedCertificatePdf->download($registration);
    }
}
