<?php

namespace App\Services\Certificates;

use App\Enums\RegistrationSource;
use App\Models\Registration;

class RegistrationCertificateIssuer
{
    /**
     * Snapshot the event's certificate template onto the registration. An event
     * with no template assigned issues nothing (certificate_template_id stays
     * null), which is the system-wide "has a certificate" gate.
     */
    public function issueFor(Registration $registration): Registration
    {
        $registration->loadMissing('event');

        $event = $registration->event;

        $registration->forceFill([
            'certificate_template_id' => $event->certificate_template_id,
            'certificate_metadata' => array_replace(
                is_array($registration->certificate_metadata) ? $registration->certificate_metadata : [],
                [
                    'source' => data_get($registration->certificate_metadata, 'source', $registration->source?->value ?? RegistrationSource::PublicForm->value),
                ],
            ),
        ])->save();

        return $registration;
    }
}
