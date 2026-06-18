<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase B — generalize the participant (a public_token QR identity + an optional
 * imported external_id) and make events composable (which modules are on, how
 * scans match, when certificates release).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->char('public_token', 26)->nullable()->after('organization_id');
            $table->string('external_id')->nullable()->after('public_token');
        });

        // Backfill a token for every existing participant before the unique index.
        DB::table('participants')->whereNull('public_token')->orderBy('id')->get(['id'])
            ->each(fn (object $row) => DB::table('participants')
                ->where('id', $row->id)
                ->update(['public_token' => (string) Str::ulid()]));

        Schema::table('participants', function (Blueprint $table): void {
            $table->unique('public_token');
            // External IDs are unique within an organization (nulls don't clash).
            $table->unique(['organization_id', 'external_id']);
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->json('modules')->nullable()->after('certificate_template_id');
            $table->string('scan_match_mode')->default('token')->after('modules');
            $table->string('certificate_release')->default('immediate')->after('scan_match_mode');
        });

        // Existing events keep their current behaviour: registration + certificate.
        DB::table('events')->whereNull('modules')->update([
            'modules' => json_encode(['registration', 'certificate']),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['modules', 'scan_match_mode', 'certificate_release']);
        });

        Schema::table('participants', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropUnique(['organization_id', 'external_id']);
            $table->dropColumn(['public_token', 'external_id']);
        });
    }
};
