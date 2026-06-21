<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop nokp from participants.
 *
 * nokp (Malaysian IC) is no longer a core participant column — it made the table
 * feel mandatory-heavy. An organization that needs it now captures it as a
 * custom field (stored in participants.details). Participant dedup and public
 * certificate lookup now key on email instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('participants', 'nokp')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Drop the soft-delete-aware unique index before the column it covers.
        if ($driver === 'mysql') {
            DB::statement('alter table `participants` drop index `participants_nokp_active_unique`');
        } else {
            DB::statement('drop index if exists "participants_nokp_active_unique"');
        }

        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn('nokp');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('participants', 'nokp')) {
            return;
        }

        Schema::table('participants', function (Blueprint $table): void {
            $table->string('nokp')->nullable()->after('email');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('alter table `participants` add unique index `participants_nokp_active_unique` ((case when `deleted_at` is null then `nokp` end))');
        } else {
            DB::statement('create unique index "participants_nokp_active_unique" on "participants" ("nokp") where "deleted_at" is null');
        }
    }
};
