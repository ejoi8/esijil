<?php

use App\Enums\RegistrationSource;
use App\Filament\Imports\ParticipantImporter;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $rows
 * @param  array<string, mixed>  $options
 */
function runParticipantImport(array $rows, array $options): void
{
    $import = Import::create([
        'user_id' => User::factory()->create()->id,
        'file_name' => 'roster.csv',
        'file_path' => 'roster.csv',
        'importer' => ParticipantImporter::class,
        'total_rows' => count($rows),
    ]);

    $columns = ['full_name', 'email', 'phone', 'external_id'];
    $map = array_combine($columns, $columns);

    foreach ($rows as $row) {
        (new ParticipantImporter($import, $map, $options))(
            array_merge(array_fill_keys($columns, null), $row),
        );
    }
}

it('imports a roster, creating participants and event registrations', function () {
    $event = Event::factory()->create(['modules' => ['attendance']]);
    $options = ['organization_id' => $event->organization_id, 'event_id' => $event->id];

    runParticipantImport([
        ['full_name' => 'Ali bin Abu', 'email' => 'ali@example.test'],
        ['full_name' => 'Siti binti Aminah', 'email' => 'siti@example.test'],
    ], $options);

    expect(Participant::where('organization_id', $event->organization_id)->count())->toBe(2)
        ->and(Registration::where('event_id', $event->id)->count())->toBe(2);

    $ali = Participant::where('email', 'ali@example.test')->first();
    expect($ali)->not->toBeNull()
        ->and($ali->public_token)->not->toBeNull()
        ->and($ali->registrations()->first()->source)->toBe(RegistrationSource::Import);
});

it('upserts on re-import without duplicating the participant', function () {
    $event = Event::factory()->create();
    $options = ['organization_id' => $event->organization_id, 'event_id' => $event->id];

    runParticipantImport([['full_name' => 'Ali', 'email' => 'ali@example.test']], $options);
    runParticipantImport([['full_name' => 'Ali Updated', 'email' => 'ali@example.test']], $options);

    expect(Participant::where('email', 'ali@example.test')->count())->toBe(1)
        ->and(Participant::where('email', 'ali@example.test')->first()->full_name)->toBe('Ali Updated')
        ->and(Registration::where('event_id', $event->id)->count())->toBe(1);
});

it('upserts by external id when present', function () {
    $event = Event::factory()->create();
    $options = ['organization_id' => $event->organization_id, 'event_id' => $event->id];

    runParticipantImport([['full_name' => 'Card Holder', 'email' => 'card@example.test', 'external_id' => 'STAFF-1']], $options);
    runParticipantImport([['full_name' => 'Renamed', 'email' => 'card@example.test', 'external_id' => 'STAFF-1']], $options);

    expect(Participant::where('external_id', 'STAFF-1')->count())->toBe(1)
        ->and(Participant::where('external_id', 'STAFF-1')->first()->full_name)->toBe('Renamed');
});
