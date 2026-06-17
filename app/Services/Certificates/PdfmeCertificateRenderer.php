<?php

namespace App\Services\Certificates;

use App\Enums\CertificatePdfRenderer;
use App\Enums\CertificateType;
use App\Models\CertificateTemplate;
use App\Models\Registration;
use App\Settings\CertificateSettings;
use Dompdf\Dompdf;
use Dompdf\Options;
use FontLib\Font;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class PdfmeCertificateRenderer
{
    /**
     * @var array<string, array{base_line_height: float}|null>
     */
    protected array $fontMetricCache = [];

    public function __construct(
        protected PdfmeTemplateFactory $templateFactory,
        protected PdfmeTemplateLegacyAssetInliner $legacyAssetInliner,
        protected PdfmeFontRegistry $fontRegistry,
        protected PdfmeNodeCertificateGenerator $pdfmeGenerator,
        protected CertificateSettings $certificateSettings,
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
        $type = CertificateType::fromMixed($registration->certificate_type)?->value ?? (string) $registration->certificate_type;

        return "{$type}-{$participant}-{$event}.pdf";
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTemplate(Registration $registration): array
    {
        $certificateTemplate = $this->currentCertificateTemplateForRegistration($registration);

        if ($certificateTemplate !== null) {
            if ($registration->certificate_template_id !== $certificateTemplate->id
                || $registration->certificate_template_key !== $certificateTemplate->key) {
                $registration->forceFill([
                    'certificate_template_id' => $certificateTemplate->id,
                    'certificate_template_key' => $certificateTemplate->key,
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

        return [
            'participant_name' => (string) $participant->full_name,
            'participant_nokp' => (string) $participant->nokp,
            'event_title' => (string) $event->title,
            'event_description' => (string) ($event->description ?? ''),
            'date_line' => (string) ($this->formatDateRange($event->starts_at?->format('d M Y'), $event->ends_at?->format('d M Y')) ?? ''),
            'time_line' => (string) ($this->formatDateRange($event->start_time_text, $event->end_time_text, ' to ') ?? ''),
            'venue' => (string) ($event->venue ?: '-'),
            'organizer' => (string) ($event->organizer_name ?? ''),
            'reference' => (string) ($registration->cert_serial_number ?: 'Pending serial number'),
            'generated_at' => now()->format('d M Y H:i'),
            'certificate_type' => CertificateType::fromMixed($registration->certificate_type)?->value ?? (string) $registration->certificate_type,
            'signature_name' => (string) ($templateSchema['signature_name'] ?? ''),
            'signature_title' => (string) ($templateSchema['signature_title'] ?? ''),
        ];
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
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    protected function generatePdf(array $template, array $inputs): string
    {
        $template = $this->fontRegistry->normalizeTemplate($template);

        if ($this->selectedRenderer() === CertificatePdfRenderer::Pdfme) {
            return $this->pdfmeGenerator->generate($template, $inputs);
        }

        $pageSize = $this->dompdfPageSize($template);

        $html = view('certificates.pdfme-dompdf', [
            'fields' => $this->dompdfFields($template, $inputs),
            'fonts' => $this->dompdfFonts(),
            'pageSize' => $pageSize,
        ])->render();

        $dompdf = new Dompdf($this->dompdfOptions());
        $dompdf->setPaper([0, 0, $this->millimetersToPoints($pageSize['width']), $this->millimetersToPoints($pageSize['height'])]);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    protected function selectedRenderer(): CertificatePdfRenderer
    {
        return CertificatePdfRenderer::fromMixed($this->certificateSettings->renderer)
            ?? CertificatePdfRenderer::Dompdf;
    }

    protected function dompdfOptions(): Options
    {
        $cachePath = storage_path('framework/cache/dompdf');

        File::ensureDirectoryExists($cachePath);

        $options = new Options;
        $options->setChroot([base_path(), public_path(), storage_path()]);
        $options->setDefaultFont('DejaVu Serif');
        $options->setFontCache($cachePath);
        $options->setFontDir($cachePath);
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsRemoteEnabled(false);
        $options->setTempDir($cachePath);

        return $options;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array{width:float,height:float}
     */
    protected function dompdfPageSize(array $template): array
    {
        $basePdf = is_array($template['basePdf'] ?? null) ? $template['basePdf'] : [];
        $width = (float) ($basePdf['width'] ?? 210);
        $height = (float) ($basePdf['height'] ?? 297);

        return [
            'width' => $width > 0 ? $width : 210.0,
            'height' => $height > 0 ? $height : 297.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     * @return array<int, array{type:string, content:string, style:string, contentStyle?:string}>
     */
    protected function dompdfFields(array $template, array $inputs): array
    {
        $fields = [];
        $page = $template['schemas'][0] ?? [];

        if (! is_array($page)) {
            return $fields;
        }

        foreach ($page as $field) {
            if (! is_array($field)) {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');

            if (! in_array($type, ['image', 'text'], true)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            $content = (string) ($inputs[$name] ?? $field['content'] ?? '');

            if ($content === '') {
                continue;
            }

            if ($type === 'image') {
                $fields[] = [
                    'type' => $type,
                    'content' => $content,
                    'style' => $this->dompdfImageStyle($field, $content),
                ];

                continue;
            }

            $fields[] = [
                'type' => $type,
                'content' => $content,
                'style' => $this->dompdfTextContainerStyle($field),
                'contentStyle' => $this->dompdfTextContentStyle($field),
            ];
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    protected function dompdfFonts(): array
    {
        $fonts = [];

        foreach ($this->fontRegistry->definitions() as $name => $definition) {
            if (! File::exists($definition['path'])) {
                continue;
            }

            $fonts[$name] = str_replace('\\', '/', $definition['path']);
        }

        return $fonts;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function dompdfFieldBaseStyles(array $field): array
    {
        $position = is_array($field['position'] ?? null) ? $field['position'] : [];
        $styles = [
            'position:absolute',
            'box-sizing:border-box',
            'left:'.$this->cssMillimeters($position['x'] ?? 0),
            'top:'.$this->cssMillimeters($position['y'] ?? 0),
            'width:'.$this->cssMillimeters($field['width'] ?? 0),
            'height:'.$this->cssMillimeters($field['height'] ?? 0),
        ];

        $rotate = (float) ($field['rotate'] ?? 0);

        if ($rotate !== 0.0) {
            $styles[] = 'transform:rotate('.$rotate.'deg)';
            $styles[] = 'transform-origin:top left';
        }

        $opacity = (float) ($field['opacity'] ?? 1);

        if ($opacity >= 0 && $opacity < 1) {
            $styles[] = 'opacity:'.$opacity;
        }

        return $styles;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function dompdfImageStyle(array $field, string $content): string
    {
        $field = $this->resolvedImageField($field, $content);
        $styles = $this->dompdfFieldBaseStyles($field);
        $styles[] = 'display:block';

        return implode(';', $styles).';';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function dompdfTextContainerStyle(array $field): string
    {
        $styles = $this->dompdfFieldBaseStyles($field);
        $styles[] = 'display:table';
        $styles[] = 'overflow:visible';

        if ($backgroundColor = $this->cssColor($field['backgroundColor'] ?? null)) {
            $styles[] = 'background-color:'.$backgroundColor;
        }

        return implode(';', $styles).';';
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function dompdfTextContentStyle(array $field): string
    {
        $fontName = str_replace('"', '', (string) ($field['fontName'] ?? PdfmeFontRegistry::BODY_FONT));
        $fontSize = (float) ($field['fontSize'] ?? 12);
        $lineHeight = (float) ($field['lineHeight'] ?? 1.2);
        $styles = [
            'display:table-cell',
            'vertical-align:'.$this->cssVerticalAlignment((string) ($field['verticalAlignment'] ?? 'top')),
            'text-align:'.$this->cssTextAlignment((string) ($field['alignment'] ?? 'left')),
            'white-space:pre-wrap',
            'word-wrap:break-word',
            'overflow-wrap:break-word',
            'font-family:"'.$fontName.'", "DejaVu Serif", serif',
            'font-size:'.$fontSize.'pt',
            'line-height:'.$lineHeight,
        ];

        $characterSpacing = (float) ($field['characterSpacing'] ?? 0);

        if ($characterSpacing !== 0.0) {
            $styles[] = 'letter-spacing:'.$characterSpacing.'pt';
        }

        if ($fontColor = $this->cssColor($field['fontColor'] ?? null)) {
            $styles[] = 'color:'.$fontColor;
        }

        if ($textDecoration = $this->cssTextDecoration($field)) {
            $styles[] = 'text-decoration:'.$textDecoration;
        }

        if ($topOffset = $this->topAlignedTextOffset(
            $fontName,
            $fontSize,
            (string) ($field['verticalAlignment'] ?? 'top'),
        )) {
            $styles[] = 'position:relative';
            $styles[] = 'top:'.$topOffset.'pt';
        }

        return implode(';', $styles).';';
    }

    protected function cssMillimeters(mixed $value): string
    {
        return ((float) $value).'mm';
    }

    protected function millimetersToPoints(float $value): float
    {
        return $value * 72 / 25.4;
    }

    protected function cssTextAlignment(string $alignment): string
    {
        return match ($alignment) {
            'center', 'right', 'justify' => $alignment,
            default => 'left',
        };
    }

    protected function cssVerticalAlignment(string $alignment): string
    {
        return match ($alignment) {
            'middle', 'center' => 'middle',
            'bottom' => 'bottom',
            default => 'top',
        };
    }

    protected function cssColor(mixed $color): ?string
    {
        $color = trim((string) $color);

        if ($color === '' || strtolower($color) === 'transparent') {
            return null;
        }

        if (preg_match('/^#[0-9a-f]{8}$/i', $color) === 1) {
            $alpha = strtolower(substr($color, 7, 2));

            return $alpha === '00' ? null : substr($color, 0, 7);
        }

        return $color;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function resolvedImageField(array $field, string $content): array
    {
        $imageDimensions = $this->imageDimensions($content);

        if ($imageDimensions === null) {
            return $field;
        }

        $position = is_array($field['position'] ?? null) ? $field['position'] : [];
        $boxWidth = (float) ($field['width'] ?? 0);
        $boxHeight = (float) ($field['height'] ?? 0);

        if ($boxWidth <= 0 || $boxHeight <= 0 || $imageDimensions['height'] <= 0) {
            return $field;
        }

        $imageRatio = $imageDimensions['width'] / $imageDimensions['height'];
        $boxRatio = $boxWidth / $boxHeight;

        if ($imageRatio > $boxRatio) {
            $resolvedWidth = $boxWidth;
            $resolvedHeight = $boxWidth / $imageRatio;
            $resolvedX = (float) ($position['x'] ?? 0);
            $resolvedY = (float) ($position['y'] ?? 0) + (($boxHeight - $resolvedHeight) / 2);
        } else {
            $resolvedWidth = $boxHeight * $imageRatio;
            $resolvedHeight = $boxHeight;
            $resolvedX = (float) ($position['x'] ?? 0) + (($boxWidth - $resolvedWidth) / 2);
            $resolvedY = (float) ($position['y'] ?? 0);
        }

        $field['position'] = [
            'x' => $resolvedX,
            'y' => $resolvedY,
        ];
        $field['width'] = $resolvedWidth;
        $field['height'] = $resolvedHeight;

        return $field;
    }

    /**
     * @return array{width: float, height: float}|null
     */
    protected function imageDimensions(string $content): ?array
    {
        $binary = $this->decodeImageContent($content);

        if ($binary !== null) {
            $dimensions = @getimagesizefromstring($binary);

            if (is_array($dimensions)) {
                return [
                    'width' => (float) $dimensions[0],
                    'height' => (float) $dimensions[1],
                ];
            }
        }

        $path = $this->imagePath($content);

        if ($path === null) {
            return null;
        }

        $dimensions = @getimagesize($path);

        if (! is_array($dimensions)) {
            return null;
        }

        return [
            'width' => (float) $dimensions[0],
            'height' => (float) $dimensions[1],
        ];
    }

    protected function decodeImageContent(string $content): ?string
    {
        if (! str_starts_with($content, 'data:')) {
            return null;
        }

        $segments = explode(',', $content, 2);

        if (count($segments) !== 2) {
            return null;
        }

        [$metadata, $payload] = $segments;

        if (str_contains($metadata, ';base64')) {
            $decoded = base64_decode($payload, true);

            return is_string($decoded) ? $decoded : null;
        }

        return rawurldecode($payload);
    }

    protected function imagePath(string $content): ?string
    {
        if (str_starts_with($content, 'file://')) {
            $path = rawurldecode(substr($content, 7));

            return File::exists($path) ? $path : null;
        }

        return File::exists($content) ? $content : null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function cssTextDecoration(array $field): ?string
    {
        $decorations = [];

        if (($field['underline'] ?? false) === true) {
            $decorations[] = 'underline';
        }

        if (($field['strikethrough'] ?? false) === true) {
            $decorations[] = 'line-through';
        }

        if ($decorations === []) {
            return null;
        }

        return implode(' ', $decorations);
    }

    protected function topAlignedTextOffset(string $fontName, float $fontSize, string $verticalAlignment): float
    {
        if ($verticalAlignment !== 'top' || $fontSize <= 0) {
            return 0.0;
        }

        $metrics = $this->fontMetrics($fontName);

        if ($metrics === null) {
            return 0.0;
        }

        return max((($metrics['base_line_height'] * $fontSize) - $fontSize) / 2, 0.0);
    }

    /**
     * @return array{base_line_height: float}|null
     */
    protected function fontMetrics(string $fontName): ?array
    {
        if (array_key_exists($fontName, $this->fontMetricCache)) {
            return $this->fontMetricCache[$fontName];
        }

        $definition = $this->fontRegistry->definitions()[$fontName] ?? null;
        $fontPath = is_array($definition) ? ($definition['path'] ?? null) : null;

        if (! is_string($fontPath) || ! File::exists($fontPath)) {
            return $this->fontMetricCache[$fontName] = null;
        }

        $font = Font::load($fontPath);

        if ($font === null) {
            return $this->fontMetricCache[$fontName] = null;
        }

        $font->parse();

        $unitsPerEm = (float) ($font->getData('head', 'unitsPerEm') ?? 0);
        $ascent = (float) ($font->getData('hhea', 'ascent') ?? 0);
        $descent = (float) ($font->getData('hhea', 'descent') ?? 0);

        $font->close();

        if ($unitsPerEm <= 0 || $ascent === 0.0) {
            return $this->fontMetricCache[$fontName] = null;
        }

        return $this->fontMetricCache[$fontName] = [
            'base_line_height' => ($ascent - $descent) / $unitsPerEm,
        ];
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
