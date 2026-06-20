<?php

use App\Filament\Resources\CustomFields\CustomFieldResource;
use App\Filament\Resources\CustomFields\Pages\CreateCustomField;
use App\Models\CustomField;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates a custom field via the admin resource form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateCustomField::class)
        ->fillForm([
            'entity' => 'participant',
            'label' => 'Jawatan',
            'key' => 'jawatan',
            'type' => 'text',
            'scope' => 'admin',
            'sort' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CustomField::query()->where('entity', 'participant')->where('key', 'jawatan')->exists())->toBeTrue();
});

it('rejects a duplicate key within the same entity', function () {
    $this->actingAs(User::factory()->create());
    CustomField::factory()->create(['entity' => 'participant', 'key' => 'jawatan']);

    Livewire::test(CreateCustomField::class)
        ->fillForm([
            'entity' => 'participant',
            'label' => 'Jawatan',
            'key' => 'jawatan',
            'type' => 'text',
            'scope' => 'admin',
            'sort' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('allows the same key on a different entity', function () {
    $this->actingAs(User::factory()->create());
    CustomField::factory()->create(['entity' => 'participant', 'key' => 'note']);

    Livewire::test(CreateCustomField::class)
        ->fillForm([
            'entity' => 'registration',
            'label' => 'Note',
            'key' => 'note',
            'type' => 'text',
            'scope' => 'admin',
            'sort' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('allows a second organization to define a participant key another organization already uses', function () {
    // Org #1 (PUSPANITA) already owns "branch" from the migration; a second org
    // must be able to define its own "branch" without hitting the unique index.
    $orgTwo = Organization::factory()->create();

    CustomField::factory()->create([
        'organization_id' => $orgTwo->id,
        'entity' => 'participant',
        'key' => 'branch',
        'event_id' => null,
    ]);

    expect(CustomField::query()->where('entity', 'participant')->where('key', 'branch')->count())->toBe(2);
});

it('scopes key uniqueness to the organization on the admin form', function () {
    $this->actingAs(User::factory()->create());

    // A different organization owns "sektor"; the active tenant (org #1) may
    // reuse the same key because uniqueness is now scoped per organization.
    $orgTwo = Organization::factory()->create();
    CustomField::factory()->create(['organization_id' => $orgTwo->id, 'entity' => 'participant', 'key' => 'sektor']);

    Livewire::test(CreateCustomField::class)
        ->fillForm([
            'entity' => 'participant',
            'label' => 'Sektor',
            'key' => 'sektor',
            'type' => 'text',
            'scope' => 'admin',
            'sort' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});

it('rejects an invalid key format', function () {
    $this->actingAs(User::factory()->create());

    // Both the literal key and the slug of the label are invalid (start with a
    // digit), so the regex rule fails regardless of label-to-key auto-fill.
    Livewire::test(CreateCustomField::class)
        ->fillForm([
            'entity' => 'participant',
            'label' => '9 Lives',
            'key' => '9_lives',
            'type' => 'text',
            'scope' => 'admin',
            'sort' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

describe('access control', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
    });

    it('lets an admin open the custom fields resource', function () {
        $this->actingAs(User::factory()->create())
            ->get(CustomFieldResource::getUrl('index'))
            ->assertSuccessful();
    });

    it('forbids staff from the custom fields resource', function () {
        $this->actingAs(User::factory()->staff()->create())
            ->get(CustomFieldResource::getUrl('index'))
            ->assertForbidden();
    });

    it('forbids a roleless user from the custom fields resource', function () {
        $this->actingAs(User::factory()->roleless()->create())
            ->get(CustomFieldResource::getUrl('index'))
            ->assertForbidden();
    });
});
