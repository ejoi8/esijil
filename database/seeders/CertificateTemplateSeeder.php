<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\Organization;
use App\Services\Certificates\PdfmeTemplateFactory;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $pdfmeTemplateFactory = app(PdfmeTemplateFactory::class);

        $schema = array_replace(CertificateTemplate::DEFAULT_SCHEMA, [
            'title' => 'Sijil Penyertaan',
            'signature_name' => 'Puan Sri Maheran Binti Jamil',
            'signature_title' => 'Yang Dipertua PUSPANITA',
        ]);

        $template = CertificateTemplate::query()->updateOrCreate(
            ['key' => 'default'],
            [
                'organization_id' => Organization::query()->where('slug', 'puspanita')->value('id'),
                'name' => 'Default Certificate',
                'schema' => $schema,
                'is_active' => true,
            ],
        );

        $template->forceFill([
            'pdfme_template' => $this->seededTemplate('default-participation')
                ?? $pdfmeTemplateFactory->fromCertificateTemplate($template),
        ])->save();

        // Backfill any template still missing a rendered layout.
        CertificateTemplate::query()
            ->whereNull('pdfme_template')
            ->orderBy('id')
            ->chunkById(100, function ($certificateTemplates) use ($pdfmeTemplateFactory): void {
                $certificateTemplates->each(function (CertificateTemplate $certificateTemplate) use ($pdfmeTemplateFactory): void {
                    $certificateTemplate->forceFill([
                        'pdfme_template' => $pdfmeTemplateFactory->fromCertificateTemplate($certificateTemplate),
                    ])->save();
                });
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function seededTemplate(string $key): ?array
    {
        $path = database_path("seeders/data/{$key}.pdfme.json");

        if (! file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $template = json_decode($contents, true);

        return is_array($template) ? $template : null;
    }
}
