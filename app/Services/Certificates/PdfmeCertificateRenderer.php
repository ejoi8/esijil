<?php

namespace App\Services\Certificates;

use App\Enums\CustomFieldEntity;
use App\Fields\CustomFields;
use App\Models\CertificateTemplate;
use App\Models\Registration;
use App\Support\QrCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orchestrates certificate rendering: resolve the template, build the variables
 * + inputs, ensure a serial, then delegate the actual PDF bytes to the injected
 * CertificateGenerator (the chosen engine). Engine-specific layout lives in the
 * generators, not here.
 */
class PdfmeCertificateRenderer
{
    public function __construct(
        protected PdfmeTemplateFactory $templateFactory,
        protected PdfmeTemplateLegacyAssetInliner $legacyAssetInliner,
        protected PdfmeFontRegistry $fontRegistry,
        protected CertificateGenerator $generator,
    ) {}

    public function render(Registration $registration): string
    {
        $registration->loadMissing('certificateTemplate', 'event.certificateTemplate', 'participant');
        $this->ensureCertificateSerialNumber($registration);

        $template = $this->resolveTemplate($registration);
        $variables = $this->buildVariables($registration);
        $inputs = $this->buildInputs($template, $variables);

        return $this->generatePdf($template, $inputs);
    }

    public function fileName(Registration $registration): string
    {
        $registration->loadMissing('event', 'participant');

        $participant = Str::slug($registration->participant->full_name);
        $event = Str::slug(Str::limit($registration->event->title, 40, ''));

        return "sijil-{$participant}-{$event}.pdf";
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTemplate(Registration $registration): array
    {
        $certificateTemplate = $this->currentCertificateTemplateForRegistration($registration);

        if ($certificateTemplate !== null) {
            if ($registration->certificate_template_id !== $certificateTemplate->id) {
                $registration->forceFill([
                    'certificate_template_id' => $certificateTemplate->id,
                ])->save();
            }

            return $this->templateFromCertificateTemplate($certificateTemplate);
        }

        return $this->templateFactory->fromSchema(CertificateTemplate::DEFAULT_SCHEMA);
    }

    /**
     * @return array<string, string>
     */
    protected function buildVariables(Registration $registration): array
    {
        $event = $registration->event;
        $participant = $registration->participant;
        $templateSchema = $this->resolveCurrentTemplateSchema($registration);

        // Custom fields that opt in via a `cert_var`. Merged first so core
        // variables (participant_name, etc.) can never be overridden.
        $detailVariables = [];
        foreach (CustomFields::definitions(CustomFieldEntity::Participant, $event) as $field) {
            if ($field->cert_var) {
                $detailVariables[$field->cert_var] = CustomFields::display($field, data_get($participant->details, $field->key));
            }
        }
        foreach (CustomFields::definitions(CustomFieldEntity::Event, $event) as $field) {
            if ($field->cert_var) {
                $detailVariables[$field->cert_var] = CustomFields::display($field, data_get($event->details, $field->key));
            }
        }
        foreach (CustomFields::definitions(CustomFieldEntity::Registration, $event) as $field) {
            if ($field->cert_var) {
                $detailVariables[$field->cert_var] = CustomFields::display($field, data_get($registration->details, $field->key));
            }
        }

        return array_merge($detailVariables, [
            'participant_name' => (string) $participant->full_name,
            'participant_nokp' => (string) $participant->nokp,
            'event_title' => (string) $event->title,
            'event_description' => (string) ($event->description ?? ''),
            'date_line' => (string) ($this->formatDateRange($event->starts_at?->format('d M Y'), $event->ends_at?->format('d M Y')) ?? ''),
            'time_line' => (string) ($this->formatDateRange($event->start_time_text, $event->end_time_text, ' to ') ?? ''),
            'venue' => (string) ($event->venue ?: '-'),
            'organizer' => (string) ($event->organizer_name ?? ''),
            'reference' => (string) ($registration->cert_serial_number ?: 'Pending serial number'),
            // A data-URI QR of the public verification page. To print it, add an
            // `image` field named `verification_qr` to the certificate template.
            'verification_qr' => $registration->cert_serial_number
                ? QrCode::dataUri(route('certificate-lookup.verify', ['serial' => $registration->cert_serial_number]))
                : '',
            'generated_at' => now()->format('d M Y H:i'),
            'signature_name' => (string) ($templateSchema['signature_name'] ?? ''),
            'signature_title' => (string) ($templateSchema['signature_title'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveCurrentTemplateSchema(Registration $registration): array
    {
        return $this->currentCertificateTemplateForRegistration($registration)?->resolvedSchema()
            ?? CertificateTemplate::DEFAULT_SCHEMA;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $variables
     * @return array<string, string>
     */
    protected function buildInputs(array $template, array $variables): array
    {
        $inputs = [];

        foreach (($template['schemas'] ?? []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            foreach ($page as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $name = (string) ($field['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $content = (string) ($field['content'] ?? '');
                $value = $content !== ''
                    ? $this->replaceVariables($content, $variables)
                    : ($variables[$name] ?? '');

                $inputs[$name] = $value;
            }
        }

        return $inputs;
    }

    /**
     * Font-normalize the template (common to every engine), then delegate the
     * actual PDF generation to the configured engine.
     *
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    protected function generatePdf(array $template, array $inputs): string
    {
        $template = $this->fontRegistry->normalizeTemplate($template);

        return $this->generator->generate($template, $inputs);
    }

    /**
     * @param  array<string, mixed> | null  $template
     */
    protected function isPdfmeTemplate(?array $template): bool
    {
        if (! is_array($template)) {
            return false;
        }

        return array_key_exists('basePdf', $template) && array_key_exists('schemas', $template);
    }

    protected function templateFromCertificateTemplate(CertificateTemplate $certificateTemplate): array
    {
        if ($this->isPdfmeTemplate($certificateTemplate->pdfme_template)) {
            /** @var array<string, mixed> $template */
            $template = $certificateTemplate->pdfme_template;
        } else {
            $template = $this->templateFactory->fromCertificateTemplate($certificateTemplate);
        }

        $syncedTemplate = $this->templateFactory->normalizeFullPageCanvas(
            $this->fontRegistry->normalizeTemplate(
                $this->legacyAssetInliner->inline($template, is_array($certificateTemplate->schema) ? $certificateTemplate->schema : []),
            ),
        );

        if ($certificateTemplate->pdfme_template != $syncedTemplate) {
            $certificateTemplate->forceFill([
                'pdfme_template' => $syncedTemplate,
            ])->save();
        }

        return $syncedTemplate;
    }

    /**
     * @return array<string, mixed>
     */
    public function templateForCertificateTemplate(CertificateTemplate $certificateTemplate): array
    {
        return $this->templateFromCertificateTemplate($certificateTemplate);
    }

    protected function currentCertificateTemplateForRegistration(Registration $registration): ?CertificateTemplate
    {
        $registration->loadMissing('certificateTemplate', 'event.certificateTemplate');

        return $registration->event?->certificateTemplate ?? $registration->certificateTemplate;
    }

    protected function ensureCertificateSerialNumber(Registration $registration): void
    {
        if (filled($registration->cert_serial_number)) {
            return;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $serialNumber = strtoupper(Str::random(12));

            try {
                $updatedRows = Registration::query()
                    ->whereKey($registration->getKey())
                    ->whereNull('cert_serial_number')
                    ->update([
                        'cert_serial_number' => $serialNumber,
                    ]);
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                continue;
            }

            if ($updatedRows === 1) {
                $registration->cert_serial_number = $serialNumber;

                return;
            }

            $registration->refresh();

            if (filled($registration->cert_serial_number)) {
                return;
            }
        }

        throw new RuntimeException('Unable to generate a unique certificate serial number.');
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (($exception->errorInfo[1] ?? null) === 1062);
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function replaceVariables(string $content, array $variables): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $matches) use ($variables): string {
            return $variables[$matches[1]] ?? '';
        }, $content);
    }

    protected function formatDateRange(?string $start, ?string $end, string $separator = ' - '): ?string
    {
        if ($start === null && $end === null) {
            return null;
        }

        if ($end === null || $start === $end) {
            return $start;
        }

        return "{$start}{$separator}{$end}";
    }
}
