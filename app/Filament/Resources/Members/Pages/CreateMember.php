<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * Add a member by email: reuse an existing account or create a new one, then
     * attach them to the current organization and assign their role in it.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            ['name' => $data['name'], 'password' => $data['password'] ?? Str::password()],
        );

        $tenant = Filament::getTenant();
        $user->organizations()->syncWithoutDetaching([$tenant->getKey()]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        $user->syncRoles([$data['role']]);

        return $user;
    }
}
