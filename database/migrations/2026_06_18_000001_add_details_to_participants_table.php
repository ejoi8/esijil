<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flexible participant fields (see FLEXIBLE_FIELDS.md): a single JSON bag for
 * optional, long-tail attributes defined in config/participant_fields.php, so a
 * new field can be added without a migration. Core columns stay first-class.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('membership_notes');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
