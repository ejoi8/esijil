<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the certificate lookup page', function () {
    $this->get(route('certificate-lookup.index'))
        ->assertSuccessful()
        ->assertSee('Semakan dan Muat Turun Sijil')
        ->assertSee('ICT PUSPANITA')
        ->assertSee('Masukkan No. KP')
        ->assertDontSee('Jumlah Rekod');
});

it('shows only the application name on the root page', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee(config('app.name'))
        ->assertDontSee('Masukkan No. KP');
});

it('uses semakan as the lookup form url', function () {
    expect(route('certificate-lookup.index', [], false))->toBe('/semakan');
});

it('redirects away from the result page without a lookup session', function () {
    $this->get(route('certificate-lookup.result'))
        ->assertRedirect(route('certificate-lookup.index'));
});

it('redirects to the result page after a successful nokp lookup', function () {
    $participant = Participant::factory()->create([
        'full_name' => 'Siti Puspanita',
        'nokp' => '900101015555',
    ]);

    $event = Event::factory()->create([
        'title' => 'Seminar Kesihatan PUSPANITA',
    ]);

    Registration::factory()->for($participant)->for($event)->create();

    $this->post(route('certificate-lookup.search'), [
        'nokp' => '900101015555',
    ])
        ->assertRedirect(route('certificate-lookup.result'))
        ->assertSessionHas('certificate_lookup_participant_id', $participant->id);

    $this->get(route('certificate-lookup.result'))
        ->assertSuccessful()
        ->assertSee('Siti Puspanita')
        ->assertSee('Seminar Kesihatan PUSPANITA')
        ->assertSee('Muat Turun');
});

it('downloads a certificate after a successful lookup session', function () {
    $participant = Participant::factory()->create([
        'full_name' => 'Siti Puspanita',
        'nokp' => '900101015555',
    ]);

    $event = Event::factory()->create([
        'title' => 'Seminar Kesihatan PUSPANITA',
    ]);

    $registration = Registration::factory()->for($participant)->for($event)->create();

    $this->post(route('certificate-lookup.search'), [
        'nokp' => '900101015555',
    ])->assertRedirect(route('certificate-lookup.result'));

    $this->get(route('certificate-lookup.download', $registration))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename=sijil-siti-puspanita-seminar-kesihatan-puspanita.pdf');

    expect($registration->refresh()->cert_serial_number)->not->toBeNull()
        ->and($registration->certificate_issued_at)->not->toBeNull();
});

it('does not show organizer or venue details on the lookup result table', function () {
    $participant = Participant::factory()->create([
        'full_name' => 'Siti Puspanita',
        'nokp' => '900101015555',
    ]);

    $event = Event::factory()->create([
        'title' => 'Bengkel Kehadiran PUSPANITA',
    ]);

    Registration::factory()->for($participant)->for($event)->create();

    $this->post(route('certificate-lookup.search'), [
        'nokp' => '900101015555',
    ])
        ->assertRedirect(route('certificate-lookup.result'));

    $this->get(route('certificate-lookup.result'))
        ->assertSuccessful()
        ->assertSee('Bengkel Kehadiran PUSPANITA')
        ->assertDontSee($event->organizer_name)
        ->assertDontSee($event->venue ?: 'Lokasi tidak dinyatakan');
});

it('forbids certificate download without a matching lookup session', function () {
    $participant = Participant::factory()->create([
        'nokp' => '900101015555',
    ]);

    $event = Event::factory()->create();
    $registration = Registration::factory()->for($participant)->for($event)->create();

    $this->get(route('certificate-lookup.download', $registration))
        ->assertForbidden();
});
