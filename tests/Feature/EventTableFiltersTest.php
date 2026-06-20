<?php

use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('filters events by certificate template on the events list page', function () {
    $this->actingAs(User::factory()->create());

    $templateA = CertificateTemplate::factory()->create([
        'name' => 'Template A',
    ]);
    $templateB = CertificateTemplate::factory()->create([
        'name' => 'Template B',
    ]);

    $matchingEvent = Event::factory()->for($templateA, 'certificateTemplate')->create();
    $otherEvent = Event::factory()->for($templateB, 'certificateTemplate')->create();

    Livewire::test(ListEvents::class)
        ->filterTable('certificate_template_id', $templateA->id)
        ->assertCanSeeTableRecords([$matchingEvent])
        ->assertCanNotSeeTableRecords([$otherEvent]);
});
