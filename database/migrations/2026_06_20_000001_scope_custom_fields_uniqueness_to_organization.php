<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make custom-field key uniqueness per-organization.
 *
 * The original index (custom_fields_entity_key_event_unique) enforced uniqueness
 * over (entity, key, COALESCE(event_id, 0)) — before custom_fields was tenant-
 * scoped. Once organization_id was added, that index wrongly stopped a *second*
 * organization from defining a field whose key another org already used (e.g.
 * every org wants its own "branch"). Re-scope uniqueness to the organization by
 * adding COALESCE(organization_id, 0) as the leading key part.
 *
 * COALESCE on both nullable parts because MySQL/SQLite treat NULLs in a unique
 * index as distinct, which would otherwise let duplicates slip through.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('alter table `custom_fields` drop index `custom_fields_entity_key_event_unique`');
            DB::statement('alter table `custom_fields` add unique index `custom_fields_org_entity_key_event_unique` ((coalesce(`organization_id`, 0)), `entity`, `key`, (coalesce(`event_id`, 0)))');
        } else {
            DB::statement('drop index if exists "custom_fields_entity_key_event_unique"');
            DB::statement('create unique index "custom_fields_org_entity_key_event_unique" on "custom_fields" ((coalesce("organization_id", 0)), "entity", "key", (coalesce("event_id", 0)))');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('alter table `custom_fields` drop index `custom_fields_org_entity_key_event_unique`');
            DB::statement('alter table `custom_fields` add unique index `custom_fields_entity_key_event_unique` (`entity`, `key`, (coalesce(`event_id`, 0)))');
        } else {
            DB::statement('drop index if exists "custom_fields_org_entity_key_event_unique"');
            DB::statement('create unique index "custom_fields_entity_key_event_unique" on "custom_fields" ("entity", "key", (coalesce("event_id", 0)))');
        }
    }
};
