<?php

namespace App\Authorization;

use App\Enums\UserRole;

/**
 * Single source of truth for permission names and the per-role grants.
 * Consumed by RolesAndPermissionsSeeder (what to create + assign) and asserted
 * by a test, so the seeder and the policies can't drift. Permission names match
 * the abilities checked in App\Policies\ResourcePolicy ("{resource}.{ability}").
 */
class Permissions
{
    /** Panel resources gated by a policy. */
    public const RESOURCES = ['branch', 'participant', 'event', 'registration', 'certificateTemplate', 'user'];

    /** Abilities every resource exposes (1:1 with ResourcePolicy methods). */
    public const ABILITIES = ['view', 'create', 'update', 'delete', 'forceDelete'];

    /** Resources a staff member may operate on (no users). */
    public const STAFF_RESOURCES = ['branch', 'participant', 'event', 'registration', 'certificateTemplate'];

    /** Abilities staff hold (no forceDelete). */
    public const STAFF_ABILITIES = ['view', 'create', 'update', 'delete'];

    /** Standalone (non-resource) permissions. */
    public const SETTINGS_MANAGE = 'settings.manage';

    public const EMAIL_LOG_VIEW = 'emailLog.view';

    /**
     * @return list<string> every permission name in the system.
     */
    public static function all(): array
    {
        return [
            ...self::resourcePermissions(self::RESOURCES, self::ABILITIES),
            self::SETTINGS_MANAGE,
            self::EMAIL_LOG_VIEW,
        ];
    }

    /**
     * @return list<string> permissions granted to a role.
     */
    public static function forRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Admin => self::all(),
            UserRole::Staff => self::resourcePermissions(self::STAFF_RESOURCES, self::STAFF_ABILITIES),
        };
    }

    /**
     * @param  list<string>  $resources
     * @param  list<string>  $abilities
     * @return list<string>
     */
    protected static function resourcePermissions(array $resources, array $abilities): array
    {
        $names = [];

        foreach ($resources as $resource) {
            foreach ($abilities as $ability) {
                $names[] = "{$resource}.{$ability}";
            }
        }

        return $names;
    }
}
