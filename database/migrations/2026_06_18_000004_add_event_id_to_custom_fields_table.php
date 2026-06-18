<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope a custom field to a single event (per-event registration questions).
 *
 * `event_id` is nullable: NULL = a global field (the common case); a value =
 * a field that only applies to that one event's registration form.
 *
 * `key` must stay unique per (entity, event scope). Because MySQL treats NULLs
 * in a unique index as distinct — which would let two global fields share a key
 * — uniqueness is enforced over COALESCE(event_id, 0):
 *  - MySQL 8: a UNIQUE functional index on (entity, key, (coalesce(event_id,0))).
 *  - SQLite:  a UNIQUE expression index on the same.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->foreignId('event_id')
                ->nullable()
                ->after('entity')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropUnique('custom_fields_entity_key_unique');
        });

        if ($driver === 'mysql') {
            DB::statement('alter table `custom_fields` add unique index `custom_fields_entity_key_event_unique` (`entity`, `key`, (coalesce(`event_id`, 0)))');
        } else {
            DB::statement('create unique index "custom_fields_entity_key_event_unique" on "custom_fields" ("entity", "key", (coalesce("event_id", 0)))');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('alter table `custom_fields` drop index `custom_fields_entity_key_event_unique`');
        } else {
            DB::statement('drop index if exists "custom_fields_entity_key_event_unique"');
        }

        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_id');
        });

        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->unique(['entity', 'key'], 'custom_fields_entity_key_unique');
        });
    }
};
