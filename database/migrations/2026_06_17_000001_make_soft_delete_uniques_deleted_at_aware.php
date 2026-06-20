<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the soft-delete models' unique constraints ignore trashed rows.
 *
 * A plain UNIQUE on a soft-deleting table also counts soft-deleted rows, and
 * MySQL treats NULLs in a unique index as distinct — so the natural key only
 * needs to be unique while the row is active (deleted_at IS NULL).
 *
 * Approach (NULL when soft-deleted, so trashed rows never collide):
 *  - MySQL 8: a UNIQUE functional index on a CASE expression. A functional
 *    index is added in-place and avoids the FK-rebuild that a STORED generated
 *    column would trigger on a table with foreign keys.
 *  - SQLite / Postgres: a partial UNIQUE index "... WHERE deleted_at IS NULL".
 *
 * On MySQL the composite UNIQUE(event_id, participant_id) also backs the
 * event_id foreign key, so a standalone event_id index is added before the
 * composite unique can be dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // participants.nokp — unique among active rows only.
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropUnique('participants_nokp_unique');
        });

        if ($driver === 'mysql') {
            DB::statement('alter table `participants` add unique index `participants_nokp_active_unique` ((case when `deleted_at` is null then `nokp` end))');
        } else {
            DB::statement('create unique index "participants_nokp_active_unique" on "participants" ("nokp") where "deleted_at" is null');
        }

        // registrations (event_id, participant_id) — unique among active rows only.
        if ($driver === 'mysql') {
            // Keep an index on event_id for its foreign key before dropping the
            // composite unique that currently backs it.
            Schema::table('registrations', function (Blueprint $table): void {
                $table->index('event_id', 'registrations_event_id_index');
            });
        }

        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropUnique('registrations_event_id_participant_id_unique');
        });

        if ($driver === 'mysql') {
            DB::statement("alter table `registrations` add unique index `registrations_event_participant_active_unique` ((case when `deleted_at` is null then concat(`event_id`, '-', `participant_id`) end))");
        } else {
            DB::statement('create unique index "registrations_event_participant_active_unique" on "registrations" ("event_id", "participant_id") where "deleted_at" is null');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('alter table `participants` drop index `participants_nokp_active_unique`');
            DB::statement('alter table `registrations` drop index `registrations_event_participant_active_unique`');
        } else {
            DB::statement('drop index if exists "participants_nokp_active_unique"');
            DB::statement('drop index if exists "registrations_event_participant_active_unique"');
        }

        // Restoring the plain uniques counts trashed rows, so first remove any
        // trashed row whose natural key collides with an active row (rollback is
        // necessarily destructive for those). Keys are pulled into PHP to avoid
        // MySQL's "can't delete from a table referenced in a subquery" error.
        $activeNokps = DB::table('participants')->whereNull('deleted_at')->pluck('nokp')->all();
        if ($activeNokps !== []) {
            DB::table('participants')
                ->whereNotNull('deleted_at')
                ->whereIn('nokp', $activeNokps)
                ->delete();
        }

        $activeRegistrationKeys = DB::table('registrations')
            ->whereNull('deleted_at')
            ->get(['event_id', 'participant_id'])
            ->map(fn ($row) => $row->event_id.'-'.$row->participant_id)
            ->all();
        if ($activeRegistrationKeys !== []) {
            DB::table('registrations')
                ->whereNotNull('deleted_at')
                ->get(['id', 'event_id', 'participant_id'])
                ->filter(fn ($row) => in_array($row->event_id.'-'.$row->participant_id, $activeRegistrationKeys, true))
                ->each(fn ($row) => DB::table('registrations')->where('id', $row->id)->delete());
        }

        Schema::table('participants', function (Blueprint $table): void {
            $table->unique('nokp', 'participants_nokp_unique');
        });

        // Restore the composite unique before dropping the standalone event_id
        // index it replaced (the composite again backs the event_id FK).
        Schema::table('registrations', function (Blueprint $table): void {
            $table->unique(['event_id', 'participant_id'], 'registrations_event_id_participant_id_unique');
        });

        if ($driver === 'mysql') {
            Schema::table('registrations', function (Blueprint $table): void {
                $table->dropIndex('registrations_event_id_index');
            });
        }
    }
};
