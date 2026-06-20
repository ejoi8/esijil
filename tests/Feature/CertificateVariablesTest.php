<?php

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Services\Certificates\CertificateVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the core certificate text variables', function () {
    expect(CertificateVariables::text())
        ->toHaveKeys(['participant_name', 'event_title', 'reference', 'signature_name', 'generated_at']);
});

it('includes admin-defined custom-field cert_vars among the text variables', function () {
    CustomField::create([
        'entity' => 'participant',
        'key' => 'jawatan',
        'label' => 'Jawatan',
        'type' => CustomFieldType::Text->value,
        'scope' => CustomFieldScope::Admin->value,
        'cert_var' => 'participant_jawatan',
        'sort' => 10,
        'active' => true,
    ]);

    expect(CertificateVariables::text())->toHaveKey('participant_jawatan');
});

it('lists the verification QR among the image fields', function () {
    expect(CertificateVariables::images())->toHaveKey('verification_qr');
});
