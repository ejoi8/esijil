<?php

use App\Enums\EventStatus;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts event status to an enum', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
    ])->fresh();

    expect($event->status)->toBe(EventStatus::Published);
});

it('creates events with a certificate template by default', function () {
    $event = Event::factory()->create()->fresh(['certificateTemplate']);

    expect($event->status)->toBeInstanceOf(EventStatus::class)
        ->and($event->certificate_template_id)->not->toBeNull()
        ->and($event->certificateTemplate)->not->toBeNull()
        ->and($event->certificateTemplate)->toBeInstanceOf(CertificateTemplate::class);
});

it('creates registrations with a matching certificate template', function () {
    $registration = Registration::factory()->create()->fresh(['certificateTemplate']);

    expect($registration->certificate_template_id)->not->toBeNull()
        ->and($registration->certificateTemplate)->not->toBeNull()
        ->and($registration->certificateTemplate)->toBeInstanceOf(CertificateTemplate::class);
});

it('resolves template keys through certificate template helpers', function () {
    $template = CertificateTemplate::factory()->create([
        'key' => 'attendance-template',
    ]);

    expect(CertificateTemplate::keyFor($template->id))->toBe('attendance-template')
        ->and(CertificateTemplate::keyFor(null))->toBeNull();
});
