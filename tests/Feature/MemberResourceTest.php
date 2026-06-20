<?php

use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lists only the current organizations members', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $outsider = User::factory()->create(['name' => 'Outsider']);
    $outsider->organizations()->detach();
    $outsider->organizations()->attach(Organization::factory()->create());

    Livewire::test(ListMembers::class)
        ->assertCanSeeTableRecords([$admin])
        ->assertCanNotSeeTableRecords([$outsider]);
});

it('adds a new member with a role', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateMember::class)
        ->fillForm([
            'name' => 'New Person',
            'email' => 'new@example.test',
            'password' => 'secret-password',
            'role' => UserRole::Staff->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->firstWhere('email', 'new@example.test');

    expect($user)->not->toBeNull()
        ->and($user->organizations()->whereKey(Filament::getTenant()->getKey())->exists())->toBeTrue()
        ->and($user->hasRole(UserRole::Staff->value))->toBeTrue();
});

it('adds an existing account as a member without duplicating it', function () {
    $this->actingAs(User::factory()->create());

    $existing = User::factory()->create(['email' => 'exists@example.test']);
    $existing->organizations()->detach();

    Livewire::test(CreateMember::class)
        ->fillForm([
            'name' => 'Ignored Name',
            'email' => 'exists@example.test',
            'role' => UserRole::Admin->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'exists@example.test')->count())->toBe(1)
        ->and($existing->fresh()->organizations()->whereKey(Filament::getTenant()->getKey())->exists())->toBeTrue();
});

it('changes a members role', function () {
    $this->actingAs(User::factory()->create());
    $member = User::factory()->staff()->create();

    Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
        ->fillForm(['name' => $member->name, 'role' => UserRole::Admin->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('removes a member without deleting the account', function () {
    $this->actingAs(User::factory()->create());
    $member = User::factory()->staff()->create();

    Livewire::test(ListMembers::class)
        ->callTableAction('remove', $member);

    expect(User::query()->find($member->id))->not->toBeNull()
        ->and($member->fresh()->organizations()->whereKey(Filament::getTenant()->getKey())->exists())->toBeFalse();
});

it('does not allow removing the last administrator', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(ListMembers::class)
        ->assertTableActionHidden('remove', $admin);
});
