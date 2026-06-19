<?php

namespace App\Filament\Resources\Members;

use App\Enums\UserRole;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-organization membership + role assignment. Backed by the User model but
 * scoped to the current tenant's members (users belong to many organizations),
 * so this never leaks users from other organizations. Gated by the user.*
 * permissions via UserPolicy (admins only).
 */
class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    // Users are not tenant-owned (many-to-many); scope to the current tenant's
    // members manually rather than via Filament's column-based tenant scoping.
    protected static bool $isScopedToTenant = false;

    protected static ?string $slug = 'members';

    protected static ?string $modelLabel = 'member';

    protected static ?string $navigationLabel = 'Members';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('roles')
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey(Filament::getTenant()?->getKey()));
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    /**
     * Number of admins in the current organization — used to guard against
     * removing or demoting the last administrator.
     */
    public static function organizationAdminCount(): int
    {
        return User::query()
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey(Filament::getTenant()?->getKey()))
            ->role(UserRole::Admin->value)
            ->count();
    }

    public static function isLastAdmin(User $user): bool
    {
        return $user->hasRole(UserRole::Admin->value) && static::organizationAdminCount() <= 1;
    }
}
