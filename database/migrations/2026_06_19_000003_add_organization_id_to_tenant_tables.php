<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stamp organization_id onto every tenant-owned table and adopt all pre-existing
 * (single-tenant) data under a default organization — PUSPANITA becomes org #1.
 * Existing users are attached to it so they retain access.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    protected array $tables = [
        'events',
        'participants',
        'registrations',
        'custom_fields',
        'certificate_templates',
    ];

    public function up(): void
    {
        // Plain indexed column (no inline FK): adding a constrained column forces
        // SQLite to rebuild the table, which drops the expression-based unique
        // indexes (e.g. custom_fields' per-event uniqueness). Filament tenancy
        // scopes via the Eloquent relationship, not a DB foreign key, so this is
        // functionally equivalent and cross-driver safe.
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('organization_id')->nullable()->after('id');
                $blueprint->index('organization_id');
            });
        }

        $organizationId = DB::table('organizations')->where('slug', 'puspanita')->value('id')
            ?? DB::table('organizations')->insertGetId([
                'name' => 'PUSPANITA Kebangsaan',
                'slug' => 'puspanita',
                'locale' => 'ms',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        }

        DB::table('users')->pluck('id')->each(function (int $userId) use ($organizationId): void {
            DB::table('organization_user')->insertOrIgnore([
                'organization_id' => $organizationId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropIndex($table.'_organization_id_index');
                $blueprint->dropColumn('organization_id');
            });
        }
    }
};
