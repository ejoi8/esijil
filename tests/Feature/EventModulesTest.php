<?php

use App\Enums\EventModule;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns each participant an unguessable public token', function () {
    $a = Participant::factory()->create();
    $b = Participant::factory()->create();

    expect($a->public_token)->toBeString()->toHaveLength(26)
        ->and($b->public_token)->not->toBe($a->public_token);
});

it('defaults a new event to the registration and certificate modules', function () {
    $event = Event::factory()->create(['modules' => null]);

    expect($event->hasModule(EventModule::Registration))->toBeTrue()
        ->and($event->hasModule(EventModule::Certificate))->toBeTrue()
        ->and($event->hasModule(EventModule::Attendance))->toBeFalse();
});

it('reads explicitly configured event modules', function () {
    $event = Event::factory()->create(['modules' => ['attendance']]);

    expect($event->hasModule(EventModule::Attendance))->toBeTrue()
        ->and($event->hasModule(EventModule::Registration))->toBeFalse();
});
