<?php

namespace App\Services\Certificates;

use App\Models\Registration;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredCertificatePdf
{
    public function __construct(
        protected PdfmeCertificateRenderer $renderer,
    ) {}

    public function download(Registration $registration): StreamedResponse
    {
        $registration->loadMissing('event', 'participant');

        // event_id/participant_id are non-null FKs, but both models soft-delete;
        // a trashed parent leaves the certificate with nothing to render, so fail
        // cleanly (404) instead of dereferencing null deep in the renderer — and
        // before render(), so a doomed download never stamps a serial.
        abort_if($registration->event === null || $registration->participant === null, 404);

        $pdf = $this->renderer->render($registration);

        if ($registration->certificate_issued_at === null) {
            $registration->forceFill([
                'certificate_issued_at' => now(),
            ])->save();
        }

        return response()->streamDownload(
            fn (): int => print $pdf,
            $this->renderer->fileName($registration),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
