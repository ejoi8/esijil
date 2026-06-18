<?php

use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('filters participants by membership status on the participants list page', function () {
    $this->actingAs(User::factory()->create());

    $member = Participant::factory()->create([
        'details' => ['membership_status' => 'member'],
    ]);
    $nonMember = Participant::factory()->create([
        'details' => ['membership_status' => 'non_member'],
    ]);

    Livewire::test(ListParticipants::class)
        ->filterTable('detail_membership_status', 'member')
        ->assertCanSeeTableRecords([$member])
        ->assertCanNotSeeTableRecords([$nonMember]);
});

it('exposes the branch custom field column and removes the placeholder email indicator on the participants list page', function () {
    $this->actingAs(User::factory()->create());

    $participant = Participant::factory()->create([
        'details' => ['branch' => 'Selangor'],
    ]);

    Livewire::test(ListParticipants::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$participant])
        ->assertTableColumnExists('details.branch')
        ->assertDontSee('Placeholder');
});
