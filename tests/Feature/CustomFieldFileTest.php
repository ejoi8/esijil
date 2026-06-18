<?php

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Enums\EventStatus;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fileEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
}

function icField(): void
{
    CustomField::create([
        'entity' => 'participant',
        'key' => 'ic_copy',
        'label' => 'Salinan Kad Pengenalan',
        'type' => CustomFieldType::File->value,
        'scope' => CustomFieldScope::PublicForm->value,
        'required' => false,
        'max_file_kb' => 5120,
        'accepted_file_types' => ['pdf', 'jpg', 'png'],
        'sort' => 60,
        'active' => true,
    ]);
}

function fileRegistrationPayload(UploadedFile $file): array
{
    return [
        'full_name' => 'Aiman',
        'email' => 'aiman@example.test',
        'nokp' => '910101015555',
        'participant_details' => [
            'membership_status' => 'member',
            'ic_copy' => $file,
        ],
    ];
}

it('stores a public file upload on the private disk', function () {
    Storage::fake('local');
    icField();
    $event = fileEvent();

    $this->post($event->publicRegistrationUrl(), fileRegistrationPayload(
        UploadedFile::fake()->create('ic.pdf', 100, 'application/pdf'),
    ))->assertRedirect();

    $path = Participant::query()->firstWhere('nokp', '910101015555')->details['ic_copy'];

    expect($path)->toStartWith('custom-fields/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('rejects a public file upload of a disallowed type', function () {
    Storage::fake('local');
    icField();
    $event = fileEvent();

    $this->post($event->publicRegistrationUrl(), fileRegistrationPayload(
        UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ))->assertSessionHasErrors('participant_details.ic_copy');
});

it('rejects a public file upload over the size limit', function () {
    Storage::fake('local');
    icField();
    $event = fileEvent();

    $this->post($event->publicRegistrationUrl(), fileRegistrationPayload(
        UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
    ))->assertSessionHasErrors('participant_details.ic_copy');
});

it('lets an admin download a stored custom-field file', function () {
    Storage::fake('local');
    Storage::disk('local')->put('custom-fields/test.pdf', 'PDFDATA');
    $participant = Participant::factory()->create(['details' => ['ic_copy' => 'custom-fields/test.pdf']]);

    $this->actingAs(User::factory()->create())
        ->get(route('auth.custom-field-file', ['entity' => 'participant', 'record' => $participant->id, 'key' => 'ic_copy']))
        ->assertOk();
});

it('returns 404 when the requested custom-field file is missing', function () {
    $participant = Participant::factory()->create(['details' => []]);

    $this->actingAs(User::factory()->create())
        ->get(route('auth.custom-field-file', ['entity' => 'participant', 'record' => $participant->id, 'key' => 'ic_copy']))
        ->assertNotFound();
});
