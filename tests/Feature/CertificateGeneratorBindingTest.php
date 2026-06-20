<?php

use App\Enums\CertificatePdfRenderer;
use App\Services\Certificates\CertificateGenerator;
use App\Services\Certificates\DompdfCertificateGenerator;
use App\Services\Certificates\PdfmeNodeCertificateGenerator;
use App\Settings\CertificateSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('both render engines implement the certificate generator contract', function () {
    expect(app(DompdfCertificateGenerator::class))->toBeInstanceOf(CertificateGenerator::class)
        ->and(app(PdfmeNodeCertificateGenerator::class))->toBeInstanceOf(CertificateGenerator::class);
});

it('resolves the DomPDF engine by default', function () {
    expect(app(CertificateGenerator::class))->toBeInstanceOf(DompdfCertificateGenerator::class);
});

it('resolves the pdfme engine when the setting selects it', function () {
    app(CertificateSettings::class)->renderer = CertificatePdfRenderer::Pdfme->value;

    expect(app(CertificateGenerator::class))->toBeInstanceOf(PdfmeNodeCertificateGenerator::class);
});
