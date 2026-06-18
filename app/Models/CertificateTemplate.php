<?php

namespace App\Models;

use Database\Factories\CertificateTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'key',
    'schema',
    'pdfme_template',
    'is_active',
])]
class CertificateTemplate extends Model
{
    /** @use HasFactory<CertificateTemplateFactory> */
    use HasFactory, SoftDeletes;

    public const DEFAULT_SCHEMA = [
        'header' => '',
        'title' => 'Sijil Penyertaan',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'Anjuran',
        'footer_text' => 'Dijana oleh eSIJIL',
        'signature_name' => '',
        'signature_title' => '',
        'background_color' => '#faf3e7',
        'border_color' => '#2b2117',
        'accent_color' => '#2b2117',
        'title_font_size' => 30,
        'name_font_size' => 20,
        'event_font_size' => 17,
        'body_font_size' => 12,
        'logo_y' => 712,
        'header_y' => 690,
        'title_y' => 610,
        'subtitle_y' => 572,
        'participant_y' => 505,
        'identity_gap_y' => 6,
        'body_intro_gap_y' => 28,
        'event_gap_y' => 56,
        'details_gap_y' => 52,
        'details_label_x' => 100,
        'details_value_x' => 200,
        'signature_line_x' => 165,
        'signature_line_y' => 112,
        'signature_name_x' => 297.5,
        'signature_name_y' => 84,
        'signature_title_y' => 66,
        'footer_y' => 40,
        'show_identity' => false,
        'show_time' => false,
        'show_venue' => false,
        'show_organizer' => true,
        'show_reference' => false,
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'pdfme_template' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(Registration::class, 'certificate_template_id');
    }

    public static function keyFor(mixed $templateId): ?string
    {
        if (blank($templateId)) {
            return null;
        }

        return static::query()
            ->whereKey($templateId)
            ->value('key');
    }

    public function resolvedSchema(): array
    {
        return array_replace(self::DEFAULT_SCHEMA, $this->schema ?? []);
    }

    public function duplicateName(): string
    {
        $baseName = "{$this->name} (Copy)";
        $candidate = $baseName;
        $suffix = 2;

        while (static::withTrashed()->where('name', $candidate)->exists()) {
            $candidate = "{$baseName} {$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    public function duplicateKey(): string
    {
        return static::uniqueDuplicateKey($this->key);
    }

    public static function uniqueDuplicateKey(string $key): string
    {
        $baseKey = Str::slug("{$key}-copy");
        $candidate = $baseKey;
        $suffix = 2;

        while (static::withTrashed()->where('key', $candidate)->exists()) {
            $candidate = "{$baseKey}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
