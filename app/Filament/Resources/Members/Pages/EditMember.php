<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Members\MemberResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name ?? UserRole::Staff->value;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($data['role'] !== UserRole::Admin->value && MemberResource::isLastAdmin($record)) {
            Notification::make()
                ->title('Cannot demote the last administrator')
                ->body('Assign another administrator to this organization first.')
                ->danger()
                ->send();

            throw new Halt;
        }

        $record->update(['name' => $data['name']]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(Filament::getTenant()?->getKey());
        $record->syncRoles([$data['role']]);

        return $record;
    }
}
