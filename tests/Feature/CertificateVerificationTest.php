<?php

use App\Models\Participant;
use App\Models\Registration;
use App\Services\Certificates\PdfmeCertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('verifies a genuine certificate by its serial', function () {
    $participant = Participant::factory()->create(['full_name' => 'Siti Aminah']);
    $registration = Registration::factory()->for($participant)->create();
    $registration->forceFill(['cert_serial_number' => 'ABC123XYZ789'])->save();

    $this->get(route('certificate-lookup.verify', ['serial' => 'ABC123XYZ789']))
        ->assertSuccessful()
        ->assertSee('Sijil Sah')
        ->assertSee('Siti Aminah')
        ->assertSee($registration->event->title);
});

it('reports an unknown serial as not found', function () {
    $this->get(route('certificate-lookup.verify', ['serial' => 'NOPE00000000']))
        ->assertSuccessful()
        ->assertSee('Sijil Tidak Dijumpai');
});

it('does not verify a registration without an issued certificate', function () {
    $registration = Registration::factory()->create();
    $registration->forceFill(['certificate_type' => null, 'cert_serial_number' => 'NOCERT123456'])->save();

    $this->get(route('certificate-lookup.verify', ['serial' => 'NOCERT123456']))
        ->assertSuccessful()
        ->assertSee('Sijil Tidak Dijumpai');
});

it('exposes a verification_qr data-uri variable from the renderer', function () {
    $registration = Registration::factory()->create();
    $registration->forceFill(['cert_serial_number' => 'QR1234567890'])->save();
    $registration->load(['event', 'participant']);

    $method = new ReflectionMethod(PdfmeCertificateRenderer::class, 'buildVariables');
    $variables = $method->invoke(app(PdfmeCertificateRenderer::class), $registration);

    expect($variables['verification_qr'])
        ->toBeString()
        ->toStartWith('data:')
        ->not->toBe('');
});
