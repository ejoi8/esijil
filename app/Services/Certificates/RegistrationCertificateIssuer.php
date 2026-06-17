<?php

namespace App\Services\Certificates;

use App\Enums\CertificateType;
use App\Enums\RegistrationSource;
use App\Models\Registration;

class RegistrationCertificateIssuer
{
    public function issueFor(Registration $registration): Registration
    {
        $registration->loadMissing('event');

        $event = $registration->event;
        $type = CertificateType::fromMixed($event->certificate_type) ?? CertificateType::ParticipationCertificate;

        $registration->forceFill([
            'certificate_type' => $type,
            'certificate_template_id' => $event->certificate_template_id,
            'certificate_template_key' => $event->template_key ?: $type->templateKey(),
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
