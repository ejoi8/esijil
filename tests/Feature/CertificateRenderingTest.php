<?php

use App\Exceptions\CertificateRenderingException;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Certificates\PdfmeCertificateRenderer;
use App\Services\Certificates\PdfmeNodeCertificateGenerator;
use App\Services\Certificates\StoredCertificatePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders certificates with dompdf by default and never invokes the node generator', function () {
    $this->mock(PdfmeNodeCertificateGenerator::class)
        ->shouldNotReceive('generate');

    $registration = Registration::factory()->create();

    $pdf = app(PdfmeCertificateRenderer::class)->render($registration);

    expect($pdf)->toStartWith('%PDF');
});

it('returns a friendly 503 without leaking renderer internals when rendering fails', function () {
    $participant = Participant::factory()->create(['nokp' => '900101015555']);
    $event = Event::factory()->create();
    $registration = Registration::factory()->for($participant)->for($event)->create();

    $this->mock(StoredCertificatePdf::class)
        ->shouldReceive('download')
        ->andThrow(CertificateRenderingException::fromGenerator('pdfme failed: SyntaxError at /usr/bin/node'));

    $this->withSession(['certificate_lookup_participant_id' => $participant->id])
        ->get(route('certificate-lookup.download', $registration))
        ->assertStatus(503)
        ->assertDontSee('SyntaxError')
        ->assertDontSee('/usr/bin/node');
});

it('returns a json 503 for api clients without leaking renderer internals', function () {
    $participant = Participant::factory()->create(['nokp' => '900101015555']);
    $event = Event::factory()->create();
    $registration = Registration::factory()->for($participant)->for($event)->create();

    $this->mock(StoredCertificatePdf::class)
        ->shouldReceive('download')
        ->andThrow(CertificateRenderingException::fromGenerator('pdfme failed: SyntaxError at /usr/bin/node'));

    $this->withSession(['certificate_lookup_participant_id' => $participant->id])
        ->getJson(route('certificate-lookup.download', $registration))
        ->assertStatus(503)
        ->assertExactJson(['message' => 'The certificate could not be generated.']);
});
