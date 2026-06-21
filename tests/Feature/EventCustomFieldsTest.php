<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Certificates\PdfmeCertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function publishedEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
}

it('saves a global event custom field via the event edit form', function () {
    $this->actingAs(User::factory()->create());
    CustomField::factory()->forEntity('event')->create(['key' => 'sponsor', 'label' => 'Sponsor']);

    $event = Event::factory()->create();

    Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->fillForm(['details' => ['sponsor' => 'Acme Bhd']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->refresh()->details)->toMatchArray(['sponsor' => 'Acme Bhd']);
});

it('shows a per-event registration field only on its own event form', function () {
    $eventA = publishedEvent();
    $eventB = publishedEvent();

    CustomField::factory()->forEntity('registration')->publicForm()->create([
        'event_id' => $eventA->id,
        'key' => 'track',
        'label' => 'Track Pilihan',
    ]);

    $this->get($eventA->publicRegistrationUrl())->assertSee('Track Pilihan');
    $this->get($eventB->publicRegistrationUrl())->assertDontSee('Track Pilihan');
});

it('stores a per-event registration field value on the registration', function () {
    $event = publishedEvent();
    CustomField::factory()->forEntity('registration')->publicForm()->create([
        'event_id' => $event->id,
        'key' => 'track',
        'label' => 'Track',
    ]);

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Aiman Rahman',
        'email' => 'aiman@example.test',
        'participant_details' => ['membership_status' => 'member'],
        'registration_details' => ['track' => 'Pagi'],
    ])->assertRedirect();

    expect(Registration::query()->firstWhere('event_id', $event->id)->details)
        ->toBe(['track' => 'Pagi']);
});

it('allows the same per-event key across two events', function () {
    $a = Event::factory()->create();
    $b = Event::factory()->create();

    CustomField::factory()->forEntity('registration')->create(['event_id' => $a->id, 'key' => 'track']);
    CustomField::factory()->forEntity('registration')->create(['event_id' => $b->id, 'key' => 'track']);

    expect(CustomField::query()->where('key', 'track')->count())->toBe(2);
});

it('exposes a per-event registration cert_var as a certificate variable', function () {
    $event = Event::factory()->create();
    CustomField::factory()->forEntity('registration')->create([
        'event_id' => $event->id,
        'key' => 'seat',
        'cert_var' => 'seat_no',
    ]);

    $participant = Participant::factory()->create();
    $registration = Registration::factory()->for($event)->for($participant)->create(['details' => ['seat' => 'B2']])
        ->load(['event', 'participant']);

    $method = new ReflectionMethod(PdfmeCertificateRenderer::class, 'buildVariables');
    $variables = $method->invoke(app(PdfmeCertificateRenderer::class), $registration);

    expect($variables['seat_no'])->toBe('B2');
});
