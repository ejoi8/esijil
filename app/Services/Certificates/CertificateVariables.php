<?php

namespace App\Services\Certificates;

use App\Models\CustomField;

/**
 * Catalogue of variables/fields a certificate template can use, surfaced in the
 * Designer sidebar. Text placeholders are inserted as {{token}} text fields and
 * filled at render; image field names must match exactly so the renderer can
 * populate them (e.g. verification_qr). Kept in one place so the Designer list
 * stays in sync with what PdfmeCertificateRenderer::buildVariables actually
 * provides — including admin-defined custom-field cert_vars.
 */
class CertificateVariables
{
    /**
     * Text placeholders: token => human label.
     *
     * @return array<string, string>
     */
    public static function text(): array
    {
        $core = [
            'participant_name' => 'Participant full name',
            'participant_nokp' => 'Participant No. KP',
            'event_title' => 'Event / program title',
            'event_description' => 'Event description',
            'date_line' => 'Date line',
            'time_line' => 'Time line',
            'venue' => 'Venue',
            'organizer' => 'Organizer name',
            'reference' => 'Certificate serial / reference',
            'signature_name' => 'Signature name',
            'signature_title' => 'Signature title',
            'generated_at' => 'Generated date/time',
        ];

        $custom = CustomField::query()
            ->whereNotNull('cert_var')
            ->where('cert_var', '!=', '')
            ->orderBy('cert_var')
            ->get()
            ->mapWithKeys(fn (CustomField $field): array => [
                $field->cert_var => ($field->label ?: $field->cert_var).' (custom field)',
            ])
            ->all();

        return array_merge($core, $custom);
    }

    /**
     * Image fields: field name => human label. The name must match exactly so
     * the renderer fills it (verification_qr is auto-filled; the rest are uploaded).
     *
     * @return array<string, string>
     */
    public static function images(): array
    {
        return [
            'logo_image' => 'Logo',
            'signature_image' => 'Signature',
            'background_image' => 'Background',
            'verification_qr' => 'Verification QR code (auto-filled)',
        ];
    }
}
