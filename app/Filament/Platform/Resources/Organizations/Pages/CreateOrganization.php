<?php

namespace App\Filament\Platform\Resources\Organizations\Pages;

use App\Filament\Platform\Resources\Organizations\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;
}
