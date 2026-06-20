<?php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use App\Services\Certificates\PdfmeTemplateFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificateTemplate>
 */
class CertificateTemplateFactory extends Factory
{
    public function configure(): static
    {
        return $this
            ->afterMaking(function (CertificateTemplate $certificateTemplate): void {
                if (array_key_exists('pdfme_template', $certificateTemplate->getAttributes())) {
                    return;
                }

                $certificateTemplate->pdfme_template = app(PdfmeTemplateFactory::class)->fromCertificateTemplate($certificateTemplate);
            })
            ->afterCreating(function (CertificateTemplate $certificateTemplate): void {
                if (array_key_exists('pdfme_template', $certificateTemplate->getAttributes())) {
                    return;
                }

                $certificateTemplate->forceFill([
                    'pdfme_template' => app(PdfmeTemplateFactory::class)->fromCertificateTemplate($certificateTemplate),
                ])->save();
            });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schema = array_replace(CertificateTemplate::DEFAULT_SCHEMA, [
            'signature_name' => 'Setiausaha Agung',
            'signature_title' => 'PUSPANITA Kebangsaan',
        ]);

        return [
            'name' => fake()->unique()->words(3, true),
            'key' => fake()->unique()->slug(3),
            'schema' => $schema,
            'is_active' => true,
        ];
    }
}
