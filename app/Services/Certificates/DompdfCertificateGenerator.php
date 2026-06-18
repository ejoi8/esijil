<?php

namespace App\Services\Certificates;

use Dompdf\Dompdf;
use Dompdf\Options;
use FontLib\Font;
use Illuminate\Support\Facades\File;

/**
 * Server-friendly certificate engine: re-creates the pdfme template layout in
 * HTML/CSS and renders it with DomPDF (no Node dependency). An approximation of
 * the pdfme "exact" output. Receives an already font-normalized template.
 */
class DompdfCertificateGenerator implements CertificateGenerator
{
    /**
     * @var array<string, array{base_line_height: float}|null>
     */
    protected array $fontMetricCache = [];

    public function __construct(
        protected PdfmeFontRegistry $fontRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, string>  $inputs
     */
    public function generate(array $template, array $inputs): string
    {
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
}
