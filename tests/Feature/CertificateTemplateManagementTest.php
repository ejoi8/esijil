<?php

use App\Enums\CertificatePdfRenderer;
use App\Filament\Resources\CertificateTemplates\CertificateTemplateResource;
use App\Filament\Resources\CertificateTemplates\Pages\CreateCertificateTemplate;
use App\Filament\Resources\CertificateTemplates\Pages\Designer;
use App\Filament\Resources\CertificateTemplates\Pages\ListCertificateTemplates;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Certificates\DompdfCertificateGenerator;
use App\Services\Certificates\PdfmeCertificateRenderer;
use App\Services\Certificates\PdfmeFontRegistry;
use App\Services\Certificates\PdfmeNodeCertificateGenerator;
use App\Services\Certificates\PdfmeTemplateFactory;
use App\Services\Certificates\PdfmeTemplateLegacyAssetInliner;
use App\Services\Certificates\RegistrationCertificateIssuer;
use App\Settings\CertificateSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('uses the current event template when a participant downloads a certificate', function () {
    $template = CertificateTemplate::factory()->create([
        'key' => 'seminar-kesihatan',
        'schema' => [
            'title' => 'SIJIL PROGRAM KESIHATAN',
            'subtitle' => 'Dengan ini disahkan bahawa',
            'body_intro' => 'telah menyertai program berikut',
            'footer_text' => 'Dijana oleh eSIJIL',
            'background_color' => '#fff7ed',
            'border_color' => '#7c2d12',
            'accent_color' => '#c2410c',
            'title_font_size' => 30,
            'name_font_size' => 24,
            'event_font_size' => 18,
            'body_font_size' => 12,
            'show_venue' => true,
            'show_organizer' => true,
            'show_reference' => true,
        ],
    ]);

    $participant = Participant::factory()->create([
        'email' => 'participant@example.com',
    ]);

    $event = Event::factory()->for($template, 'certificateTemplate')->create();

    $registration = Registration::factory()->for($participant)->for($event)->create();
    $registration->forceFill([
        'certificate_metadata' => null,
    ])->save();

    $this->post(route('certificate-lookup.search'), [
        'email' => $participant->email,
    ])->assertRedirect(route('certificate-lookup.result'));

    $this->get(route('certificate-lookup.download', $registration))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $registration->refresh();
    $resolvedTemplate = app(PdfmeCertificateRenderer::class)->templateForCertificateTemplate($template->fresh());
    $titleField = collect($resolvedTemplate['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');
    $participantField = collect($resolvedTemplate['schemas'][0] ?? [])
        ->firstWhere('name', 'participant_name');
    $signatureField = collect($resolvedTemplate['schemas'][0] ?? [])
        ->firstWhere('name', 'signature_name');

    expect($registration->certificate_template_id)->toBe($template->id)
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('SIJIL PROGRAM KESIHATAN')
        ->and($participantField)->toBeArray()
        ->and($participantField['fontName'])->toBe(PdfmeFontRegistry::BODY_FONT)
        ->and($signatureField)->toBeArray()
        ->and($signatureField['content'])->toBe('')
        ->and($registration->certificate_metadata)->toBeNull();
});

it('stores designer template json from the pdfme designer page', function () {
    $this->actingAs(User::factory()->create());

    $template = CertificateTemplate::factory()->create([
        'pdfme_template' => null,
    ]);

    $designerPayload = [
        'basePdf' => [
            'width' => 210,
            'height' => 297,
            'padding' => [0, 0, 0, 0],
        ],
        'schemas' => [[
            [
                'name' => 'participant_name',
                'type' => 'text',
                'content' => '{{participant_name}}',
                'position' => [
                    'x' => 28,
                    'y' => 72,
                ],
                'width' => 154,
                'height' => 12,
                'alignment' => 'center',
                'verticalAlignment' => 'middle',
                'fontSize' => 14,
                'lineHeight' => 1.3,
                'characterSpacing' => 0,
                'fontColor' => '#1f1a17',
                'backgroundColor' => '#ffffff00',
            ],
        ]],
    ];

    Livewire::test(Designer::class, ['record' => $template->getRouteKey()])
        ->call('saveDesigner', json_encode($designerPayload, JSON_THROW_ON_ERROR));

    $savedTemplate = $template->refresh()->pdfme_template;
    $participantField = collect($savedTemplate['schemas'][0] ?? [])
        ->firstWhere('name', 'participant_name');

    expect($savedTemplate)->toHaveKeys(['basePdf', 'schemas'])
        ->and($participantField)->toBeArray()
        ->and($participantField['fontName'])->toBe(PdfmeFontRegistry::BODY_FONT);
});

it('duplicates a certificate template with a fresh key from the list page', function () {
    $this->actingAs(User::factory()->create());

    $template = CertificateTemplate::factory()->create([
        'name' => 'Template Asal',
        'key' => 'template-asal',
    ]);

    Livewire::test(ListCertificateTemplates::class)
        ->callTableAction('duplicate', $template);

    $duplicate = CertificateTemplate::query()
        ->whereKeyNot($template->id)
        ->sole();

    expect($duplicate->name)->toBe('Template Asal (Copy)')
        ->and($duplicate->key)->toBe('template-asal-copy')
        ->and($duplicate->schema)->toEqual($template->schema)
        ->and($duplicate->pdfme_template)->toEqual($template->pdfme_template)
        ->and(CertificateTemplate::query()->count())->toBe(2);
});

it('creates default designer templates with a full page background image slot', function () {
    $template = app(PdfmeTemplateFactory::class)->fromSchema(CertificateTemplate::DEFAULT_SCHEMA);
    $backgroundField = collect($template['schemas'][0] ?? [])
        ->firstWhere('name', 'background_image');

    expect($template['basePdf']['padding'])->toBe([0, 0, 0, 0])
        ->and($backgroundField)->toBeArray()
        ->and($backgroundField['position'])->toBe(['x' => 0, 'y' => 0])
        ->and($backgroundField['width'])->toBe(210.0)
        ->and($backgroundField['height'])->toBe(297.0);
});

it('uses the saved default participation layout as the designer reset baseline', function () {
    $this->actingAs(User::factory()->create());

    $savedDefaultTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'SAVED DEFAULT PARTICIPATION DESIGN',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $template = CertificateTemplate::factory()->create([
        'pdfme_template' => $savedDefaultTemplate,
    ]);

    $component = Livewire::test(Designer::class, ['record' => $template->getRouteKey()]);
    $titleField = collect($component->instance()->defaultTemplateData['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('SAVED DEFAULT PARTICIPATION DESIGN');
});

it('normalizes existing inset background images to the full page canvas', function () {
    $template = [
        'basePdf' => [
            'width' => 210,
            'height' => 297,
            'padding' => [10, 10, 10, 10],
        ],
        'schemas' => [[
            [
                'name' => 'certificate_title',
                'type' => 'text',
                'content' => 'Sijil Penyertaan',
                'position' => ['x' => 20, 'y' => 44],
                'width' => 170,
                'height' => 18,
                'fontSize' => 24,
            ],
            [
                'name' => 'background_image',
                'type' => 'image',
                'content' => 'data:image/png;base64,test',
                'position' => ['x' => 10, 'y' => 10],
                'width' => 190,
                'height' => 277,
            ],
        ]],
    ];

    $normalizedTemplate = app(PdfmeTemplateFactory::class)->normalizeFullPageCanvas($template);
    $backgroundField = collect($normalizedTemplate['schemas'][0] ?? [])
        ->firstWhere('name', 'background_image');

    expect($normalizedTemplate['basePdf']['padding'])->toBe([0, 0, 0, 0])
        ->and($normalizedTemplate['schemas'][0][0]['name'])->toBe('background_image')
        ->and($backgroundField['position'])->toBe(['x' => 0, 'y' => 0])
        ->and($backgroundField['width'])->toBe(210.0)
        ->and($backgroundField['height'])->toBe(297.0);
});

it('issues public registration certificates from the current designer layout', function () {
    $designerTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'DESIGNER PARTICIPATION TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $template = CertificateTemplate::factory()->create([
        'key' => 'designer-participation',
        'pdfme_template' => $designerTemplate,
    ]);

    $event = Event::factory()->for($template, 'certificateTemplate')->create();

    $registration = Registration::factory()->for($event)->create();

    $registration = app(RegistrationCertificateIssuer::class)->issueFor($registration);
    $titleField = collect(app(PdfmeCertificateRenderer::class)->templateForCertificateTemplate($template)['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($registration->certificate_template_id)->toBe($template->id)
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('DESIGNER PARTICIPATION TITLE');
});

it('refreshes a linked issued certificate when the designer layout changes', function () {
    $oldTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'OLD DESIGNER TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);
    $newTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'NEW DESIGNER TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $template = CertificateTemplate::factory()->create([
        'pdfme_template' => $oldTemplate,
    ]);
    $event = Event::factory()->for($template, 'certificateTemplate')->create();
    $registration = Registration::factory()->for($event)->create([
        'certificate_template_id' => $template->id,
    ]);

    $template->forceFill([
        'pdfme_template' => $newTemplate,
    ])->save();

    $renderer = app(PdfmeCertificateRenderer::class);
    $pdf = $renderer->render($registration);
    $titleField = collect($renderer->templateForCertificateTemplate($template->fresh())['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($pdf)->toStartWith('%PDF')
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('NEW DESIGNER TITLE');
});

it('uses the configured pdfme generator when the certificate renderer is set to pdfme', function () {
    $template = CertificateTemplate::factory()->create();
    $event = Event::factory()->for($template, 'certificateTemplate')->create();
    $registration = Registration::factory()->for($event)->create();

    $settings = app(CertificateSettings::class);
    $settings->renderer = CertificatePdfRenderer::Pdfme->value;
    $settings->save();

    $this->mock(PdfmeNodeCertificateGenerator::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('%PDF-pdfme');

    expect(app(PdfmeCertificateRenderer::class)->render($registration))
        ->toBe('%PDF-pdfme');
});

it('refreshes an issued certificate when the event switches to a newer default template', function () {
    $oldTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'OLD SWITCH TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);
    $newTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'NEW SWITCH TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $originalTemplate = CertificateTemplate::factory()->create([
        'pdfme_template' => $oldTemplate,
    ]);
    $replacementTemplate = CertificateTemplate::factory()->create([
        'pdfme_template' => $newTemplate,
    ]);
    $event = Event::factory()->for($originalTemplate, 'certificateTemplate')->create();
    $registration = Registration::factory()->for($event)->create([
        'certificate_template_id' => $originalTemplate->id,
    ]);

    $event->forceFill([
        'certificate_template_id' => $replacementTemplate->id,
    ])->save();

    $renderer = app(PdfmeCertificateRenderer::class);
    $pdf = $renderer->render($registration);
    $titleField = collect($renderer->templateForCertificateTemplate($replacementTemplate->fresh())['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($pdf)->toStartWith('%PDF')
        ->and($registration->refresh()->certificate_template_id)->toBe($replacementTemplate->id)
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('NEW SWITCH TITLE');
});

it('refreshes an issued certificate from the event default template when the designer layout changes', function () {
    $oldTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'OLD EVENT DEFAULT TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);
    $newTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'NEW EVENT DEFAULT TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $template = CertificateTemplate::factory()->create([
        'pdfme_template' => $oldTemplate,
    ]);
    $event = Event::factory()->for($template, 'certificateTemplate')->create();
    $registration = Registration::factory()->for($event)->create([
        'certificate_template_id' => null,
    ]);

    $template->forceFill([
        'pdfme_template' => $newTemplate,
    ])->save();

    $renderer = app(PdfmeCertificateRenderer::class);
    $pdf = $renderer->render($registration);
    $titleField = collect($renderer->templateForCertificateTemplate($template->fresh())['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($pdf)->toStartWith('%PDF')
        ->and($registration->refresh()->certificate_template_id)->toBe($template->id)
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('NEW EVENT DEFAULT TITLE');
});

it('falls back to the registration-linked template when the event has no template', function () {
    $linkedTemplate = app(PdfmeTemplateFactory::class)->fromSchema([
        'title' => 'LINKED TEMPLATE TITLE',
        'subtitle' => 'Dengan ini disahkan bahawa',
        'body_intro' => 'telah menyertai program berikut',
        'organizer_heading' => 'ANJURAN',
    ]);

    $template = CertificateTemplate::factory()->create([
        'pdfme_template' => $linkedTemplate,
    ]);
    $event = Event::factory()->create([
        'certificate_template_id' => null,
    ]);
    $registration = Registration::factory()->for($event)->create([
        'certificate_template_id' => $template->id,
    ]);

    $renderer = app(PdfmeCertificateRenderer::class);
    $pdf = $renderer->render($registration);
    $titleField = collect($renderer->templateForCertificateTemplate($template->fresh())['schemas'][0] ?? [])
        ->firstWhere('name', 'certificate_title');

    expect($pdf)->toStartWith('%PDF')
        ->and($registration->refresh()->certificate_template_id)->toBe($template->id)
        ->and($titleField)->toBeArray()
        ->and($titleField['content'])->toBe('LINKED TEMPLATE TITLE');
});

it('does not add template snapshot metadata when issuing a certificate', function () {
    $template = CertificateTemplate::factory()->create();
    $event = Event::factory()->for($template, 'certificateTemplate')->create();
    $registration = Registration::factory()->for($event)->source('public_form')->create([
        'certificate_metadata' => null,
    ]);

    $registration = app(RegistrationCertificateIssuer::class)->issueFor($registration);

    expect($registration->certificate_metadata)->toBeArray()
        ->and(data_get($registration->certificate_metadata, 'source'))->toBe('public_form')
        ->and(data_get($registration->certificate_metadata, 'template_schema_snapshot'))->toBeNull();
});

it('uses dompdf-safe text styles that stay closer to the pdfme designer output', function () {
    $renderer = app(DompdfCertificateGenerator::class);
    $template = [
        'basePdf' => [
            'width' => 210,
            'height' => 297,
            'padding' => [0, 0, 0, 0],
        ],
        'schemas' => [[
            [
                'name' => 'participant_name',
                'type' => 'text',
                'content' => '{{participant_name}}',
                'position' => ['x' => 24, 'y' => 79],
                'width' => 162,
                'height' => 10,
                'alignment' => 'center',
                'verticalAlignment' => 'top',
                'fontName' => PdfmeFontRegistry::BODY_FONT,
                'fontSize' => 12,
                'lineHeight' => 1.1,
                'characterSpacing' => 0,
                'fontColor' => '#1f1a17',
                'backgroundColor' => '#ffffff00',
                'underline' => true,
                'strikethrough' => true,
            ],
        ]],
    ];

    $method = new ReflectionMethod($renderer, 'dompdfFields');
    $method->setAccessible(true);

    $fields = $method->invoke($renderer, $template, [
        'participant_name' => 'Nama Peserta',
    ]);

    $participantField = collect($fields)
        ->first(fn (array $field): bool => $field['type'] === 'text' && $field['content'] === 'Nama Peserta');

    expect($participantField)->toBeArray()
        ->and($participantField['style'])->toContain('display:table')
        ->and($participantField['style'])->toContain('overflow:visible')
        ->and($participantField['style'])->not->toContain('display:flex')
        ->and($participantField['contentStyle'])->toContain('display:table-cell')
        ->and($participantField['contentStyle'])->toContain('vertical-align:top')
        ->and($participantField['contentStyle'])->toContain('word-wrap:break-word')
        ->and($participantField['contentStyle'])->toContain('overflow-wrap:break-word')
        ->and($participantField['contentStyle'])->toContain('text-decoration:underline line-through')
        ->and($participantField['contentStyle'])->toContain('position:relative')
        ->and($participantField['contentStyle'])->toContain('top:');
});

it('preserves image aspect ratio for certificate logos in the pdf renderer', function () {
    $renderer = app(DompdfCertificateGenerator::class);
    $template = [
        'basePdf' => [
            'width' => 210,
            'height' => 297,
            'padding' => [0, 0, 0, 0],
        ],
        'schemas' => [[
            [
                'name' => 'logo_image',
                'type' => 'image',
                'content' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WnL8i8AAAAASUVORK5CYII=',
                'position' => ['x' => 80, 'y' => 12],
                'width' => 50,
                'height' => 28,
            ],
        ]],
    ];

    $method = new ReflectionMethod($renderer, 'dompdfFields');
    $method->setAccessible(true);

    $fields = $method->invoke($renderer, $template, []);

    $logoField = collect($fields)
        ->first(fn (array $field): bool => $field['type'] === 'image');

    expect($logoField)->toBeArray()
        ->and($logoField['style'])->toContain('left:91mm')
        ->and($logoField['style'])->toContain('top:12mm')
        ->and($logoField['style'])->toContain('width:28mm')
        ->and($logoField['style'])->toContain('height:28mm')
        ->and($logoField['style'])->not->toContain('object-fit:contain');
});

it('does not expose asset management on the designer page anymore', function () {
    $this->actingAs(User::factory()->create());

    $template = CertificateTemplate::factory()->create();

    Livewire::test(Designer::class, ['record' => $template->getRouteKey()])
        ->assertActionDoesNotExist('manageAssets');
});

it('inlines legacy asset paths into the designer template for backward compatibility', function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');
    Storage::disk('public')->put(
        'certificate-templates/logos/default-logo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WnL8i8AAAAASUVORK5CYII=', true),
    );

    $template = CertificateTemplate::factory()->create([
        'schema' => [
            'logo_path' => 'certificate-templates/logos/default-logo.png',
        ],
        'pdfme_template' => [
            'basePdf' => [
                'width' => 210,
                'height' => 297,
                'padding' => [0, 0, 0, 0],
            ],
            'schemas' => [[
                [
                    'name' => 'participant_name',
                    'type' => 'text',
                    'content' => '{{participant_name}}',
                    'position' => [
                        'x' => 28,
                        'y' => 72,
                    ],
                    'width' => 154,
                    'height' => 12,
                    'alignment' => 'center',
                    'verticalAlignment' => 'middle',
                    'fontSize' => 14,
                    'lineHeight' => 1.3,
                    'characterSpacing' => 0,
                    'fontColor' => '#1f1a17',
                    'backgroundColor' => '#ffffff00',
                ],
            ]],
        ],
    ]);

    $component = Livewire::test(Designer::class, ['record' => $template->getRouteKey()]);
    $fieldNames = collect($component->instance()->templateData['schemas'][0])
        ->pluck('name')
        ->all();
    $logoField = collect($component->instance()->templateData['schemas'][0])
        ->firstWhere('name', 'logo_image');
    $participantField = collect($component->instance()->templateData['schemas'][0])
        ->firstWhere('name', 'participant_name');

    expect($fieldNames)
        ->toContain('logo_image')
        ->toContain('participant_name')
        ->and($logoField)->toBeArray()
        ->and($logoField['content'])->toStartWith('data:image')
        ->and($participantField)->toBeArray()
        ->and($participantField['fontName'])->toBe(PdfmeFontRegistry::BODY_FONT);
});

it('keeps template images self contained when no legacy asset path exists', function () {
    $template = app(PdfmeTemplateFactory::class)->fromSchema(CertificateTemplate::DEFAULT_SCHEMA);
    $inlinedTemplate = app(PdfmeTemplateLegacyAssetInliner::class)->inline($template, []);

    expect($inlinedTemplate)->toBe($template);
});

it('normalizes missing font names in the template', function () {
    $template = [
        'basePdf' => [
            'width' => 210,
            'height' => 297,
            'padding' => [0, 0, 0, 0],
        ],
        'schemas' => [[
            [
                'name' => 'certificate_title',
                'type' => 'text',
                'content' => 'Sijil Penyertaan',
                'position' => ['x' => 20, 'y' => 44],
                'width' => 170,
                'height' => 18,
                'fontSize' => 24,
            ],
            [
                'name' => 'participant_name',
                'type' => 'text',
                'content' => '{{participant_name}}',
                'position' => ['x' => 24, 'y' => 79],
                'width' => 162,
                'height' => 10,
                'fontSize' => 12,
            ],
        ]],
    ];

    $normalizedTemplate = app(PdfmeFontRegistry::class)->normalizeTemplate($template);
    $titleField = collect($normalizedTemplate['schemas'][0])->firstWhere('name', 'certificate_title');
    $participantField = collect($normalizedTemplate['schemas'][0])->firstWhere('name', 'participant_name');

    expect($titleField)->toBeArray()
        ->and($titleField['fontName'])->toBe(PdfmeFontRegistry::TITLE_FONT)
        ->and($participantField)->toBeArray()
        ->and($participantField['fontName'])->toBe(PdfmeFontRegistry::BODY_FONT);
});

it('redirects new templates to the designer and removes the standalone view page', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(CreateCertificateTemplate::class)
        ->fillForm([
            'name' => 'Editable Template',
            'key' => 'editable-template',
            'is_active' => true,
        ])
        ->call('create');

    $template = CertificateTemplate::query()
        ->where('key', 'editable-template')
        ->sole();

    $component->assertRedirect(CertificateTemplateResource::getUrl('designer', ['record' => $template]));

    expect(CertificateTemplateResource::getPages())->not->toHaveKey('view');
});
